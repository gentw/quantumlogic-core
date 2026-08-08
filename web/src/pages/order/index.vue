<script setup>
import { loadStripe } from '@/utils/stripe'

definePage({
  meta: { layout: 'blank', public: true },
})

const services = ref([])
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

const selectedLines = computed(() =>
  Object.entries(selected.value)
    .filter(([, quantity]) => quantity > 0)
    .map(([id, quantity]) => ({ id: Number(id), quantity })),
)

const load = async () => {
  try {
    services.value = (await $api('/v1/public/services')).data
  } catch (err) {
    console.error('Failed to load the catalogue:', err)
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
      },
    })
  } catch (err) {
    console.error('Failed to price the selection:', err)
  }
}

watch([selectedLines, () => buyer.value.country_code, () => buyer.value.vat_id], refreshQuote, { deep: true })

const start = async () => {
  working.value = true
  errorMessage.value = ''
  try {
    const res = await $api('/v1/public/checkout/start', {
      method: 'POST',
      body: { ...buyer.value, services: selectedLines.value },
    })

    orderNumber.value = res.order_number

    // The email already had an account: the order joined it and can be paid
    // right here, but the password typed above was ignored — they sign in with
    // the one they already have.
    existingAccount.value = !!res.existing_account

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
        <template v-if="step !== 'pay'">
          <h4 class="text-h4 mb-2">Order services</h4>
          <p class="text-body-1 mb-6">
            Pick what you need, pay a 50% deposit by card, and we get to work.
            Your client account is created along the way.
          </p>

          <VCard
            v-for="service in services"
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
          has been added to it. Pay below as usual — then
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
            <div class="d-flex justify-space-between mb-1">
              <span>VAT</span><span>{{ formatMoney(quote.vat_total) }}</span>
            </div>
            <div class="d-flex justify-space-between font-weight-medium mb-3">
              <span>Total</span><span>{{ formatMoney(quote.total_gross) }}</span>
            </div>
            <VAlert type="info" variant="tonal" density="compact">
              {{ quote.deposit_percent }}% deposit now
              ({{ formatMoney(quote.total_gross * quote.deposit_percent / 100) }}),
              the rest on delivery.
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
              Continue to payment
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
