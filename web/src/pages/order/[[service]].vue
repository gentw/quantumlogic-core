<script setup>
import { loadStripe } from '@/utils/stripe'

definePage({
  meta: { layout: 'blank', public: true },
})

const route = useRoute()

// `/order/{slug}` is a personalised order link: one service, optionally at a
// price only the holder of `?coupon=` gets. `/order` on its own is the normal
// catalogue. `?manual-payment` drops the card rail and bills by transfer.
const slug = computed(() => route.params.service || '')
const couponCode = computed(() => route.query.coupon || '')
const transferOnly = computed(() => 'manual-payment' in route.query)

const services = ref([])
const catalogueLoaded = ref(false)
const selected = ref({}) // id -> quantity
const buyer = ref({
  name: '', email: '', company: '', vat_id: '', country_code: 'AT',
  password: '', password_confirmation: '',
})
const showPassword = ref(false)

// Mirrors the FormRequest (min:8, confirmed) so the buyer finds out before the
// round trip; the server rules remain the actual check.
const passwordError = computed(() =>
  buyer.value.password && buyer.value.password.length < 8 ? 'At least 8 characters.' : '')

const confirmError = computed(() =>
  buyer.value.password_confirmation && buyer.value.password_confirmation !== buyer.value.password
    ? 'The passwords do not match.'
    : '')

const detailsComplete = computed(() =>
  !!buyer.value.name && !!buyer.value.email
  && buyer.value.password.length >= 8
  && buyer.value.password_confirmation === buyer.value.password)
const quote = ref(null)
const step = ref('pick') // pick -> details -> pay

// EU members first — they decide VAT treatment and are the common case here.
const countryItems = countryOptions()

// A UID only changes the tax outcome inside the EU (reverse charge); outside it
// the sale is zero-rated regardless, so don't imply the field matters.
const showsVatIdHint = computed(() => isEuCountry(buyer.value.country_code) && buyer.value.country_code !== 'AT')
const vatIdPlaceholder = computed(() => `${buyer.value.country_code || 'AT'}123456789`)
const working = ref(false)
const errorMessage = ref('')
const existingAccount = ref(false)

let stripe = null
let elements = null
const cardReady = ref(false)
const orderNumber = ref('')
const bankDetails = ref(null)
const invoiceNumber = ref('')
const payToken = ref(null)

const selectedLines = computed(() =>
  Object.entries(selected.value)
    .filter(([, quantity]) => quantity > 0)
    .map(([id, quantity]) => ({ id: Number(id), quantity })),
)

/** The deep-linked service, once the catalogue has arrived. */
const linkedService = computed(() =>
  slug.value ? services.value.find(s => s.slug === slug.value) ?? null : null)

// A link to a service that is no longer orderable must not silently sell
// something else — say so and fall back to the open catalogue.
const linkBroken = computed(() => catalogueLoaded.value && !!slug.value && !linkedService.value)

// A personalised link shows only what it was sent for; the discount attached to
// it applies to that service alone, so a fuller list would misprice itself.
const visibleServices = computed(() => linkedService.value ? [linkedService.value] : services.value)

const load = async () => {
  try {
    services.value = (await $api('/v1/public/services')).data
    if (linkedService.value) selected.value[linkedService.value.id] = 1
  } catch (err) {
    console.error('Failed to load the catalogue:', err)
  } finally {
    catalogueLoaded.value = true
  }
}

onMounted(load)

const toggle = service => {
  if (selected.value[service.id]) delete selected.value[service.id]
  else selected.value[service.id] = 1
}

const refreshQuote = async () => {
  if (!selectedLines.value.length) {
    quote.value = null

    return
  }

  try {
    quote.value = await $api('/v1/public/checkout/quote', {
      method: 'POST',
      body: {
        services: selectedLines.value,
        country_code: buyer.value.country_code || undefined,
        vat_id: buyer.value.vat_id || undefined,
        coupon: couponCode.value || undefined,
      },
    })
  } catch (err) {
    console.error('Failed to price the selection:', err)
  }
}

// The server is deliberately silent about *why* a code failed, so all we can
// tell the buyer is that this one is not being applied — better than letting
// them reach the payment step still expecting a discount.
const couponRejected = computed(() => !!couponCode.value && !!quote.value && !quote.value.coupon)

watch([selectedLines, () => buyer.value.country_code, () => buyer.value.vat_id], refreshQuote, { deep: true })

const start = async () => {
  working.value = true
  errorMessage.value = ''
  try {
    const res = await $api('/v1/public/checkout/start', {
      method: 'POST',
      body: {
        ...buyer.value,
        services: selectedLines.value,
        coupon: couponCode.value || undefined,
        payment_method: transferOnly.value ? 'transfer' : 'card',
      },
    })

    orderNumber.value = res.order_number

    // The email already had an account: the order joined it and can be paid
    // right here, but the password typed above was ignored — they sign in with
    // the one they already have.
    existingAccount.value = !!res.existing_account

    // Transfer: the invoice is issued and payable, but nothing is charged here.
    // No card is loaded at all, so there is no Stripe step to reach.
    if (res.payment_method === 'transfer') {
      bankDetails.value = res.bank_details
      invoiceNumber.value = res.invoice_number
      payToken.value = res.pay_token
      step.value = 'transfer'

      return
    }

    const stripeInstance = await loadStripe()

    if (!stripeInstance) {
      errorMessage.value = 'Card payments are not available right now.'

      return
    }

    stripe = stripeInstance
    elements = stripe.elements({ clientSecret: res.client_secret })
    step.value = 'pay'
    await nextTick()
    elements.create('payment').mount('#guest-payment-element')
    cardReady.value = true
  } catch (err) {
    errorMessage.value = err.data?.message ?? 'The order could not be started.'
  } finally {
    working.value = false
  }
}

// Transfer receipt. VFileInput models an array even for a single file.
const proofFile = ref([])
const proofNote = ref('')
const proofError = ref('')
const proofUploaded = ref(false)
const uploadingProof = ref(false)

const PROOF_TYPES = ['application/pdf', 'image/jpeg', 'image/png']
const PROOF_MAX_BYTES = 10 * 1024 * 1024

/**
 * Send the bank slip against the pay-link token. Mirrors PaymentProofRequest
 * client-side so an obvious reject costs no upload; the server rules remain
 * the actual check, and it content-sniffs rather than trusting the extension.
 */
const uploadProof = async () => {
  const file = proofFile.value[0]

  if (!file) return

  if (!PROOF_TYPES.includes(file.type)) {
    proofError.value = 'Please attach a PDF, JPG or PNG.'

    return
  }
  if (file.size > PROOF_MAX_BYTES) {
    proofError.value = 'That file is over 10 MB.'

    return
  }

  uploadingProof.value = true
  proofError.value = ''

  const body = new FormData()

  body.append('file', file)
  if (proofNote.value) body.append('note', proofNote.value)

  try {
    await $api(`/v1/public/invoices/${payToken.value}/proof`, { method: 'POST', body })
    proofUploaded.value = true
  } catch (err) {
    proofError.value = err.data?.message ?? 'The upload failed. Please try again.'
  } finally {
    uploadingProof.value = false
  }
}

const pay = async () => {
  if (!stripe || !elements) return
  working.value = true
  errorMessage.value = ''

  const { error } = await stripe.confirmPayment({
    elements,
    confirmParams: {
      return_url: `${window.location.origin}/order/success?order=${orderNumber.value}`,
    },
  })

  if (error) {
    errorMessage.value = error.message
    working.value = false
  }
}
</script>

<template>
  <VContainer class="py-10" style="max-inline-size: 64rem;">
    <div class="d-flex align-center justify-space-between mb-8">
      <AppLogo />
      <VBtn variant="text" :to="{ name: 'login' }">Log in</VBtn>
    </div>

    <VRow>
      <!-- Catalogue + form -->
      <VCol cols="12" md="7">
        <template v-if="step === 'pick'">
          <h4 class="text-h4 mb-2">{{ linkedService ? linkedService.name : 'Order services' }}</h4>
          <p class="text-body-1 mb-6">
            <template v-if="linkedService">
              This is a personalised offer prepared for you.
            </template>
            <template v-else>
              Pick what you need,
            </template>
            pay a 50% deposit
            {{ transferOnly ? 'by bank transfer' : 'by card' }}, and we get to work.
            Your client account is created along the way.
          </p>

          <VAlert v-if="linkBroken" type="warning" variant="tonal" class="mb-6">
            That offer link is no longer available, so here is the full catalogue instead.
          </VAlert>

          <VAlert v-if="couponRejected" type="warning" variant="tonal" class="mb-6">
            The discount code <strong>{{ couponCode }}</strong> is not valid for this
            order — it may have expired or already been used. The prices below are the
            standard ones.
          </VAlert>

          <VCard
            v-for="service in visibleServices"
            :key="service.id"
            variant="outlined"
            class="mb-3"
            :class="{ 'border-primary': selected[service.id] }"
            @click="toggle(service)"
          >
            <VCardText class="d-flex align-center gap-4">
              <VCheckboxBtn :model-value="!!selected[service.id]" @click.stop="toggle(service)" />
              <div class="flex-grow-1">
                <div class="font-weight-medium">{{ service.name }}</div>
                <div class="text-body-2">{{ service.description }}</div>
              </div>
              <div class="text-end">
                <div class="font-weight-medium">
                  {{ formatMoney(service.price_net) }}
                  <span v-if="service.billing_interval" class="text-body-2">/ {{ service.billing_interval === 'yearly' ? 'year' : 'month' }}</span>
                </div>
                <div class="text-body-2">net</div>
              </div>
            </VCardText>
          </VCard>

          <VExpandTransition>
            <div v-if="selectedLines.length" class="mt-6">
              <h6 class="text-h6 mb-4">Your details</h6>
              <VRow dense>
                <VCol cols="12" sm="6">
                  <VTextField v-model="buyer.name" label="Full name" class="mb-3" />
                  <VTextField v-model="buyer.email" label="Email" type="email" class="mb-3" />
                  <VTextField
                    v-model="buyer.password"
                    label="Choose a password"
                    :type="showPassword ? 'text' : 'password'"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    autocomplete="new-password"
                    :error-messages="passwordError ? [passwordError] : []"
                    hint="At least 8 characters — this is how you'll sign in to the client portal."
                    persistent-hint
                    class="mb-3"
                    @click:append-inner="showPassword = !showPassword"
                  />
                  <VTextField
                    v-model="buyer.password_confirmation"
                    label="Confirm password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    :error-messages="confirmError ? [confirmError] : []"
                    class="mb-3"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="buyer.company" label="Company (optional)" class="mb-3" />
                  <!-- Country decides VAT treatment, so it is a fixed list: a typo
                       here would silently change the tax on the invoice. -->
                  <VAutocomplete
                    v-model="buyer.country_code"
                    :items="countryItems"
                    label="Country"
                    placeholder="Start typing…"
                    auto-select-first
                    class="mb-3"
                  />
                  <VTextField
                    v-model="buyer.vat_id"
                    label="UID (optional)"
                    :placeholder="vatIdPlaceholder"
                    :hint="showsVatIdHint ? 'With a valid EU VAT ID this sale is reverse-charged at 0% VAT.' : undefined"
                    persistent-hint
                  />
                </VCol>
              </VRow>
            </div>
          </VExpandTransition>
        </template>

        <!-- SEPA transfer: the invoice is issued, nothing is charged here -->
        <template v-else-if="step === 'transfer'">
          <h4 class="text-h4 mb-2">Transfer the deposit</h4>
          <p class="text-body-1 mb-6">
            Order {{ orderNumber }} is placed and invoice {{ invoiceNumber }} is on its
            way to you by email. Transfer the deposit using the details below — quote the
            reference so we can match it — and we start as soon as it lands.
          </p>

          <VCard v-if="bankDetails" variant="outlined" class="mb-4">
            <VCardText>
              <div class="d-flex flex-wrap gap-6">
                <div class="flex-grow-1">
                  <div class="mb-3">
                    <div class="text-body-2">Account holder</div>
                    <div class="font-weight-medium">{{ bankDetails.account_holder }}</div>
                  </div>
                  <div class="mb-3">
                    <div class="text-body-2">IBAN</div>
                    <div class="font-weight-medium">{{ bankDetails.iban }}</div>
                  </div>
                  <div class="mb-3">
                    <div class="text-body-2">BIC</div>
                    <div class="font-weight-medium">{{ bankDetails.bic }}</div>
                  </div>
                  <div class="mb-3">
                    <div class="text-body-2">Reference — please include it</div>
                    <div class="font-weight-medium">{{ bankDetails.reference }}</div>
                  </div>
                  <div>
                    <div class="text-body-2">Amount</div>
                    <div class="font-weight-medium">{{ formatMoney(bankDetails.amount) }}</div>
                  </div>
                </div>
                <div v-if="bankDetails.epc_qr_png" class="text-center">
                  <img
                    :src="bankDetails.epc_qr_png"
                    alt="EPC QR — scan with your banking app"
                    width="160"
                    height="160"
                  >
                  <div class="text-body-2 mt-1">Scan with your banking app</div>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Receipt upload, inline and without signing in: the pay-link token
               issued above is the credential. Nothing here settles the invoice. -->
          <VCard v-if="payToken" variant="outlined">
            <VCardItem>
              <VCardTitle class="text-h6">Already transferred?</VCardTitle>
              <VCardSubtitle class="text-wrap">
                Upload the receipt or bank confirmation and we will match it against
                your order. PDF, JPG or PNG, up to 10 MB.
              </VCardSubtitle>
            </VCardItem>
            <VCardText>
              <VAlert v-if="proofUploaded" type="success" variant="tonal" class="mb-0">
                Receipt received. We will confirm it and get started — you can close
                this page. A copy of the invoice is in your email.
              </VAlert>

              <template v-else>
                <VFileInput
                  v-model="proofFile"
                  label="Receipt"
                  accept="application/pdf,image/jpeg,image/png"
                  prepend-icon=""
                  prepend-inner-icon="tabler-paperclip"
                  :error-messages="proofError ? [proofError] : []"
                  class="mb-3"
                />
                <VTextarea
                  v-model="proofNote"
                  label="Note (optional)"
                  rows="2"
                  class="mb-3"
                />
                <VBtn
                  block
                  color="primary"
                  :loading="uploadingProof"
                  :disabled="!proofFile.length"
                  @click="uploadProof"
                >
                  Send the receipt
                </VBtn>
              </template>
            </VCardText>
          </VCard>
        </template>

        <template v-else>
          <h4 class="text-h4 mb-2">Pay the deposit</h4>
          <p class="text-body-1 mb-6">Order {{ orderNumber }} — the balance is due on delivery.</p>
          <VCard variant="outlined" class="mb-4">
            <VCardText>
              <div id="guest-payment-element" />
              <VProgressCircular v-if="!cardReady" indeterminate class="d-block mx-auto my-4" />
            </VCardText>
          </VCard>
          <VBtn block color="primary" :loading="working" :disabled="!cardReady" @click="pay">
            Pay {{ formatMoney(quote?.total_gross * (quote?.deposit_percent ?? 50) / 100) }}
          </VBtn>
        </template>

        <VAlert v-if="errorMessage" type="error" variant="tonal" density="compact" class="mt-4">
          {{ errorMessage }}
        </VAlert>

        <VAlert v-if="existingAccount" type="info" variant="tonal" class="mt-4">
          You already have an account with this email, so order {{ orderNumber }}
          has been added to it. Settle it as usual — then
          <RouterLink :to="{ name: 'login' }">sign in</RouterLink> with your
          existing password to follow it. (The password you entered above was
          not applied.)
        </VAlert>
      </VCol>

      <!-- Quote pane -->
      <VCol cols="12" md="5">
        <VCard v-if="quote">
          <VCardText>
            <h6 class="text-h6 mb-4">Summary</h6>
            <div v-for="line in quote.lines" :key="line.name" class="d-flex justify-space-between mb-2">
              <span>{{ line.name }} × {{ line.quantity }}</span>
              <span>{{ formatMoney(line.gross) }}</span>
            </div>
            <VDivider class="my-3" />
            <div class="d-flex justify-space-between mb-1">
              <span>Net</span><span>{{ formatMoney(quote.subtotal_net) }}</span>
            </div>
            <!-- Shown against the list price, so the buyer sees what came off
                 rather than just a smaller number. -->
            <div v-if="quote.coupon" class="d-flex justify-space-between mb-1 text-success">
              <span>
                Discount
                <VChip size="x-small" label class="ms-1">{{ quote.coupon.code }}</VChip>
              </span>
              <span>−{{ formatMoney(quote.discount_total) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span>VAT</span><span>{{ formatMoney(quote.vat_total) }}</span>
            </div>
            <div class="d-flex justify-space-between font-weight-medium mb-3">
              <span>Total</span><span>{{ formatMoney(quote.total_gross) }}</span>
            </div>
            <VAlert type="info" variant="tonal" density="compact">
              {{ quote.deposit_percent }}% deposit
              ({{ formatMoney(quote.total_gross * quote.deposit_percent / 100) }})
              {{ transferOnly ? 'by bank transfer' : 'now' }}, the rest on delivery.
            </VAlert>

            <VBtn
              v-if="step === 'pick'"
              block
              color="primary"
              class="mt-4"
              :loading="working"
              :disabled="!detailsComplete"
              @click="start"
            >
              {{ transferOnly ? 'Place the order' : 'Continue to payment' }}
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
