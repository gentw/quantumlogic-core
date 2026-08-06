<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import { useTrialFingerprint } from '@/composables/useTrialFingerprint'
import WiseCardDialog from '@/views/client/pay-now/WiseCardDialog.vue'

const isDialogVisible = ref(false)
const route = useRoute()
const router = useRouter()
const $toast = useToast()
const { get: getTrialFingerprint } = useTrialFingerprint()

const radioContent = computed(() => {
  if (isTrial.value) {
    return [
      { title: 'Credit Card (Verification Only)', value: 'credit card', images: 'tabler-credit-card' },
    ]
  }

  return [
    { title: 'Credit Card', value: 'credit card', images: 'tabler-credit-card' },
    { title: 'PayPal', value: 'paypal', images: 'tabler-brand-paypal' },
    { title: 'Bank Transfer', value: 'wise', images: 'tabler-building-bank' },
  ]
})

const selectedRadio = ref('credit card')
const selectedCountry = ref('USA')
const isPricingPlanDialogVisible = ref(false)

const email = ref('')
const password = ref('')
const zipCode = ref('')

const isCreditCardModalVisible = ref(false)
const isPaypalModalVisible = ref(false)
const isSubmitting = ref(false)
const servicePrice = ref(0.0)

const subscriptionPrice = computed(() => (isTrial.value ? 0 : servicePrice.value))
const tax = computed(() => (isTrial.value ? 0 : 4.99))
const total = computed(() => subscriptionPrice.value + tax.value)

const cardNumber = ref('')
const cardHolder = ref('')
const expDate = ref('')
const cvv = ref('')

const invoice = ref(null)
const isTrial = ref(false)

const proceedWithPayment = () => {
  if (selectedRadio.value === 'paypal') {
    isPaypalModalVisible.value = true
  } else {
    isCreditCardModalVisible.value = true
  }
}

const buildCardToken = () => {
  // SIMULATED tokenization. Replace with real gateway tokenization (Stripe/Adyen/etc.)
  // before going live; this stub exists so trial-start has SOMETHING to store
  // instead of inventing a value server-side.
  const last4 = (cardNumber.value || '').replace(/\s+/g, '').slice(-4)
  return `tok_sim_${Date.now()}_${last4 || 'xxxx'}`
}

const startTrialFlow = async () => {
  isSubmitting.value = true
  try {
    await $api('/v1/client/sub/startTrial', {
      method: 'POST',
      headers: {
        'X-Trial-Fingerprint': getTrialFingerprint(),
      },
      body: {
        package_id: invoice.value?.package_id ?? 1,
        payment_method: 'cc',
        payment_token: buildCardToken(),
        payment_brand: 'visa',
      },
    })

    $toast.success('Trial started. Welcome aboard!')
    isCreditCardModalVisible.value = false
    router.push('/client')
  } catch (err) {
    const data = err?.response?._data
    if (data?.trial_blocked) {
      $toast.error(data.message ?? 'Trial already used.')
    } else if (data?.errors) {
      $toast.error(Object.values(data.errors).flat().join(' '))
    } else {
      $toast.error(data?.message ?? 'Failed to start trial.')
    }
  } finally {
    isSubmitting.value = false
  }
}

const submitCreditCardPayment = async () => {
  if (isTrial.value) {
    await startTrialFlow()
    return
  }

  $toast.info('Credit-card auto-charge integration is pending. Use PayPal for now.')
  isCreditCardModalVisible.value = false
}

const submitPaypalPayment = () => {
  isPaypalModalVisible.value = false
  payNow()
}

const payNow = async () => {
  try {
    const res = await $api('/v1/paypal/payment', {
      method: 'POST',
      body: {
        amount: subscriptionPrice.value,
        currency: 'EUR',
        invoice_id: route.params.id,
      },
    })

    if (res.approval_url) {
      window.location.href = res.approval_url
    } else {
      $toast.error('Failed to create PayPal payment.')
    }
  } catch (err) {
    $toast.error('The payment could not be processed.')
  }
}

onMounted(async () => {
  try {
    const data = await $api(`/v1/client/invoice/${route.params.id}`, { method: 'GET' })

    invoice.value = data
    isTrial.value = !!data.is_trial
    servicePrice.value = parseFloat(data.amount)

    if (isTrial.value) {
      selectedRadio.value = 'credit card'
    }
  } catch (err) {
    if (err.response?.status === 404) {
      router.replace('/client/plans-billing')
    }
  }
})
</script>

<template>
  <div class="payment-page">
    <VContainer>
      <div class="d-flex justify-center align-center payment-card">
        <VCard width="100%">
          <VRow>
            <VCol cols="12" md="8" :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'">
              <VCardText class="pa-8 pe-5">
                <div>
                  <h4 class="text-h4 mb-2">Checkout</h4>
                  <div class="text-body-1">
                    All plans include 40+ advanced tools and features. Choose the best plan.
                  </div>
                  <VAlert
                    v-if="isTrial"
                    type="info"
                    variant="tonal"
                    density="comfortable"
                    class="mt-4"
                  >
                    Your card is required to start the trial. <strong>You will not be charged</strong>
                    during the 7-day trial period — the card is stored only as a payment method.
                  </VAlert>
                </div>

                <CustomRadios
                  v-model:selected-radio="selectedRadio"
                  :radio-content="radioContent"
                  :grid-column="{ cols: '12', sm: '4' }"
                  class="my-8"
                  :disabled="isTrial"
                >
                  <template #default="{ item }">
                    <div class="d-flex align-center gap-x-4 ms-3">
                      <VIcon size="24" :icon="item.images" />
                      <h6 class="text-h6">{{ item.title }}</h6>
                    </div>
                  </template>
                </CustomRadios>

                <div v-if="selectedRadio == 'wise'">
                  <VCard>
                    <VCardText style="padding:0!important">
                      <p class="text-body-1 mb-4">
                        <strong>Please note</strong> that invoices cannot be considered paid until the transaction
                        is successfully processed and reflected in our banking system.
                        After receiving an invoice, payment must be completed within
                        <strong>3 days</strong>. Otherwise, the payment will be considered
                        <strong>overdue</strong>.
                      </p>

                      <VDivider class="my-4" />

                      <h5 class="text-h5 mb-3">Bank Acc:</h5>
                      <div class="text-body-1">
                        <p><strong>Company Name:</strong> BiteMyBytes</p>
                        <p><strong>Address:</strong> Mon Maqi 13, Gjakova, 50000</p>
                        <p><strong>Account Number:</strong> 150500000000000</p>
                        <p><strong>IBAN:</strong> XK05 1111 1111 0111 01</p>
                        <p><strong>SWIFT:</strong> RBKOXKPR</p>
                      </div>
                    </VCardText>
                  </VCard>
                </div>

                <VDivider class="my-4" />

                <div class="mt-4 text-sm text-gray-600">
                  Note: We do <strong>NOT</strong> store your credit card information. You will be asked to
                  enter it for each payment. Automatic payments only apply if you use PayPal and keep it linked.
                </div>
              </VCardText>
            </VCol>

            <VCol cols="12" md="4">
              <VCardText class="pa-8 ps-5">
                <div class="mb-8">
                  <h4 class="text-h4 mb-2">Order Summary</h4>
                  <div class="text-body-1">Review your subscription and total before payment.</div>
                </div>

                <VCard flat color="rgba(var(--v-theme-on-surface), var(--v-hover-opacity))">
                  <VCardText>
                    <div class="text-body-1">A simple start for everyone</div>
                    <h1 class="text-h1 my-4">${{ subscriptionPrice }}</h1>
                    <RouterLink :to='"/client/invoice/change-plan/" + route.params.id'>
                      <VBtn variant="tonal" block @click="isPricingPlanDialogVisible = !isPricingPlanDialogVisible">
                        Change Plan
                      </VBtn>
                    </RouterLink>
                  </VCardText>
                </VCard>

                <div class="my-5">
                  <div class="d-flex justify-space-between mb-2">
                    <span>Subscription</span>
                    <h6 class="text-h6">${{ subscriptionPrice.toFixed(2) }}</h6>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span>Tax</span>
                    <h6 class="text-h6">${{ tax.toFixed(2) }}</h6>
                  </div>
                  <VDivider class="my-4" />
                  <div class="d-flex justify-space-between">
                    <span>Total</span>
                    <h6 class="text-h6">${{ total.toFixed(2) }}</h6>
                  </div>
                </div>

                <WiseCardDialog v-model="isDialogVisible" />

                <VBtn block color="success" class="mb-8" :loading="isSubmitting" @click="proceedWithPayment">
                  <template #append>
                    <VIcon icon="tabler-arrow-right" class="flip-in-rtl" />
                  </template>
                  {{ isTrial ? 'Activate Trial' : 'Proceed With Payment' }}
                </VBtn>
              </VCardText>
            </VCol>
          </VRow>
        </VCard>
      </div>
    </VContainer>

    <!-- Credit-card capture modal -->
    <VDialog v-model="isCreditCardModalVisible" max-width="480">
      <VCard>
        <VCardTitle>{{ isTrial ? 'Save Card to Start Trial' : 'Card Details' }}</VCardTitle>
        <VCardText>
          <p v-if="isTrial" class="mb-4 text-body-2">
            Your card is verified and saved as a payment method. You will not be charged during the trial.
          </p>
          <VTextField v-model="cardNumber" label="Card Number" class="mb-2" />
          <VTextField v-model="cardHolder" label="Cardholder Name" class="mb-2" />
          <div class="d-flex gap-3">
            <VTextField v-model="expDate" label="MM/YY" />
            <VTextField v-model="cvv" label="CVV" />
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isCreditCardModalVisible = false">Cancel</VBtn>
          <VBtn color="primary" :loading="isSubmitting" @click="submitCreditCardPayment">
            {{ isTrial ? 'Activate Trial' : 'Pay Now' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- PayPal redirect confirmation -->
    <VDialog v-model="isPaypalModalVisible" max-width="420">
      <VCard>
        <VCardTitle>Continue with PayPal</VCardTitle>
        <VCardText>
          You will be redirected to PayPal to authorize the ${{ total.toFixed(2) }} payment.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isPaypalModalVisible = false">Cancel</VBtn>
          <VBtn color="primary" @click="submitPaypalPayment">Continue</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.payment-card { margin-block: 0 !important; }
.payment-page { @media (min-width: 600px) and (max-width: 960px) { .v-container { padding-inline: 2rem !important; } } }
.payment-card .custom-radio .v-radio { margin-block-start: 0 !important; }
</style>
