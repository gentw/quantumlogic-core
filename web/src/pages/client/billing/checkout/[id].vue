<script setup>
import { loadStripe } from '@/utils/stripe'

const route = useRoute('client-billing-checkout-id')
const router = useRouter()
const billingApi = useBillingApi()

const invoice = ref(null)
const bankDetails = ref(null)
const rail = ref('card')
const working = ref(false)
const errorMessage = ref('')

// Optional partial amount handed over by the pay page.
const amount = computed(() => {
  const requested = Number(route.query.amount)
  const due = Number(invoice.value?.amount_due ?? 0)

  return requested > 0 && requested < due ? requested : due
})

// Nothing left to charge — a settled, cancelled or credit-noted invoice. Checked
// before mounting anything: asking for a PaymentIntent here returns 422, which
// surfaced as a bare "This invoice cannot be paid." over an empty card form.
const isSettled = computed(() =>
  !!invoice.value && (Number(invoice.value.amount_due) <= 0 || ['paid', 'cancelled'].includes(invoice.value.status)),
)

// Stripe Payment Element state
let stripe = null
let elements = null
const cardReady = ref(false)

// Bank transfer proof upload
const proofDialog = ref(false)
const proofFile = ref(null)
const proofNote = ref('')
const proofDone = ref(false)

const rails = [
  { value: 'card', title: 'Pay by card', icon: 'tabler-credit-card' },
  { value: 'paypal', title: 'Pay with PayPal', icon: 'tabler-brand-paypal' },
  { value: 'transfer', title: 'Pay by bank transfer', icon: 'tabler-building-bank' },
]

const load = async () => {
  try {
    invoice.value = await billingApi.invoice(route.params.id)
  } catch (err) {
    console.error('Failed to load invoice:', err)
    errorMessage.value = 'This invoice could not be loaded.'
  }
}

const mountCard = async () => {
  errorMessage.value = ''
  cardReady.value = false

  try {
    const [stripeInstance, intent] = await Promise.all([
      loadStripe(),
      billingApi.stripeIntent(invoice.value.id, amount.value < invoice.value.amount_due ? amount.value : undefined),
    ])

    if (!stripeInstance) {
      errorMessage.value = 'Card payments are not available right now.'

      return
    }

    stripe = stripeInstance
    elements = stripe.elements({ clientSecret: intent.client_secret })
    elements.create('payment').mount('#stripe-payment-element')
    cardReady.value = true
  } catch (err) {
    console.error('Failed to start the card payment:', err)
    errorMessage.value = err.data?.message ?? 'Could not start the card payment.'
  }
}

const loadBankDetails = async () => {
  try {
    bankDetails.value = await billingApi.bankDetails(invoice.value.id)
  } catch (err) {
    console.error('Failed to load bank details:', err)
    errorMessage.value = 'Bank details could not be loaded.'
  }
}

watch(rail, async selected => {
  if (isSettled.value) return
  errorMessage.value = ''
  if (selected === 'card' && !cardReady.value) await mountCard()
  if (selected === 'transfer' && !bankDetails.value) await loadBankDetails()
})

const payByCard = async () => {
  if (!stripe || !elements) return
  working.value = true
  errorMessage.value = ''

  // The webhook settles the invoice; this return URL only navigates.
  const { error } = await stripe.confirmPayment({
    elements,
    confirmParams: {
      return_url: `${window.location.origin}/client/billing/invoices/${invoice.value.id}`,
    },
  })

  if (error) {
    errorMessage.value = error.message
    working.value = false
  }
}

const payWithPayPal = async () => {
  working.value = true
  errorMessage.value = ''
  try {
    const res = await billingApi.paypalCreate(
      invoice.value.id,
      amount.value < invoice.value.amount_due ? amount.value : undefined,
    )

    window.location.href = res.approval_url
  } catch (err) {
    console.error('Failed to start the PayPal payment:', err)
    errorMessage.value = err.data?.message ?? 'Could not start the PayPal payment.'
    working.value = false
  }
}

const submitProof = async () => {
  if (!proofFile.value) return
  working.value = true
  errorMessage.value = ''
  try {
    await billingApi.uploadProof(invoice.value.id, proofFile.value, proofNote.value)
    proofDialog.value = false
    proofDone.value = true
  } catch (err) {
    console.error('Failed to upload the proof:', err)
    errorMessage.value = err.data?.message ?? 'The proof could not be uploaded.'
  } finally {
    working.value = false
  }
}

onMounted(async () => {
  await load()
  if (invoice.value && !isSettled.value) await mountCard()
})
</script>

<template>
  <section v-if="invoice">
    <VRow>
      <!-- Order summary pane -->
      <VCol cols="12" md="5">
        <div class="d-flex align-center gap-2 mb-6">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            :to="{ name: 'client-billing-invoices-id', params: { id: invoice.id } }"
          />
          <AppLogo />
        </div>

        <div class="text-body-1 mb-1">Total to pay</div>
        <h3 class="text-h3 mb-6">{{ formatMoney(amount) }}</h3>

        <VCard variant="outlined">
          <VCardText>
            <div class="d-flex justify-space-between mb-3">
              <span>NR</span>
              <span class="font-weight-medium">{{ invoice.invoice_number }}</span>
            </div>
            <div class="d-flex justify-space-between mb-3">
              <span>Amount due</span>
              <span class="font-weight-medium">{{ formatMoney(invoice.amount_due) }}</span>
            </div>
            <div v-if="amount < invoice.amount_due" class="d-flex justify-space-between mb-3">
              <span>Paying now</span>
              <span class="font-weight-medium">{{ formatMoney(amount) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-3">
              <span>Due date</span>
              <span class="font-weight-medium">{{ formatDate(invoice.due_at) }}</span>
            </div>
            <div v-if="invoice.reference" class="d-flex justify-space-between">
              <span>Reference</span>
              <span class="font-weight-medium">{{ invoice.reference }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Rail chooser pane -->
      <VCol cols="12" md="7">
        <!-- Nothing owed: say so plainly instead of offering rails that will refuse. -->
        <template v-if="isSettled">
          <h5 class="text-h5 mb-6">Nothing left to pay</h5>
          <VAlert
            :type="invoice.status === 'cancelled' ? 'info' : 'success'"
            variant="tonal"
            class="mb-6"
          >
            {{ invoice.status === 'cancelled'
              ? 'This invoice was cancelled, so there is nothing to pay.'
              : 'This invoice is settled in full. Thank you.' }}
          </VAlert>
          <VBtn :to="{ name: 'client-billing-invoices-id', params: { id: invoice.id } }" color="primary">
            View invoice
          </VBtn>
          <VBtn :to="{ name: 'client-billing' }" variant="text" class="ms-2">
            Back to billing
          </VBtn>
        </template>

        <template v-else>
        <h5 class="text-h5 mb-6">Choose how to pay</h5>

        <VRadioGroup v-model="rail" class="mb-4">
          <VRadio
            v-for="option in rails"
            :key="option.value"
            :value="option.value"
            class="mb-2"
          >
            <template #label>
              <VIcon :icon="option.icon" class="me-2" />
              {{ option.title }}
            </template>
          </VRadio>
        </VRadioGroup>

        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ errorMessage }}
        </VAlert>

        <!-- Card (Stripe Payment Element) -->
        <template v-if="rail === 'card'">
          <VCard variant="outlined" class="mb-4">
            <VCardText>
              <div id="stripe-payment-element" />
              <VProgressCircular v-if="!cardReady && !errorMessage" indeterminate class="d-block mx-auto my-4" />
            </VCardText>
          </VCard>
          <VBtn
            block
            color="primary"
            :loading="working"
            :disabled="!cardReady"
            @click="payByCard"
          >
            Pay {{ formatMoney(amount) }}
          </VBtn>
        </template>

        <!-- PayPal -->
        <template v-else-if="rail === 'paypal'">
          <VCard variant="outlined" class="mb-4">
            <VCardText>
              You will be redirected to PayPal to approve the payment, then
              brought back here.
            </VCardText>
          </VCard>
          <VBtn block color="primary" :loading="working" @click="payWithPayPal">
            Continue to PayPal
          </VBtn>
        </template>

        <!-- SEPA bank transfer -->
        <template v-else>
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
                </div>
                <div class="text-center">
                  <img
                    :src="bankDetails.epc_qr_png"
                    alt="EPC QR — scan with your banking app"
                    width="160"
                    height="160"
                  >
                  <div class="text-body-2 mt-1">Scan with your banking app</div>
                </div>
              </div>

              <VAlert type="info" variant="tonal" density="compact" class="mt-2">
                The invoice stays open until we receive the transfer or your
                proof of payment is accepted.
              </VAlert>
            </VCardText>
          </VCard>

          <VAlert v-if="proofDone" type="success" variant="tonal" class="mb-4">
            Proof received — we will confirm the payment shortly.
          </VAlert>
          <VBtn
            v-else
            block
            color="primary"
            variant="tonal"
            prepend-icon="tabler-upload"
            @click="proofDialog = true"
          >
            Upload payment proof
          </VBtn>
        </template>
        </template>
      </VCol>
    </VRow>

    <!-- Proof upload dialog -->
    <VDialog v-model="proofDialog" max-width="480">
      <VCard title="Upload payment proof">
        <VCardText>
          <VFileInput
            v-model="proofFile"
            label="Bank slip (PDF, JPG or PNG, max 10 MB)"
            accept="application/pdf,image/jpeg,image/png"
            prepend-icon="tabler-paperclip"
            class="mb-4"
          />
          <VTextarea
            v-model="proofNote"
            label="Note (optional)"
            rows="2"
            auto-grow
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="proofDialog = false">
            Cancel
          </VBtn>
          <VBtn color="primary" :loading="working" :disabled="!proofFile" @click="submitProof">
            Submit
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>

  <section v-else-if="errorMessage">
    <VAlert type="error" variant="tonal">{{ errorMessage }}</VAlert>
  </section>
</template>
