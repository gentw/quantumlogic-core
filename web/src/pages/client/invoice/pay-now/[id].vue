<script setup>
import { ref } from 'vue'

import paypalDark from '@images/icons/payments/img/paypal-dark.png'
import paypalLight from '@images/icons/payments/img/paypal-light.png'
import visaDark from '@images/icons/payments/img/visa-dark.png'
import visaLight from '@images/icons/payments/img/visa-light.png'

import WiseCardDialog from '@/views/client/pay-now/WiseCardDialog.vue';

const isDialogVisible = ref(false)
const route = useRoute()
const visa = useGenerateImageVariant(visaLight, visaDark)
const paypal = useGenerateImageVariant(paypalLight, paypalDark)
const router = useRouter()

const radioContent = computed(() => {
  if (isTrial.value) {
    return [
      { title: 'Credit Card (Verification Only)', value: 'credit card', images: 'tabler-credit-card' }
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

// Billing info (front-end only)
const email = ref('')
const password = ref('')
const zipCode = ref('')

// Modal controls
const isCreditCardModalVisible = ref(false)
const isPaypalModalVisible = ref(false)
const servicePrice = ref(0.00);
// Dummy order summary
const subscriptionPrice = computed(() => {
  return isTrial.value ? 0 : servicePrice.value
})

const tax = computed(() => isTrial.value ? 0 : 4.99)
const total = computed(() => subscriptionPrice.value + tax.value)

// Credit card modal fields (simulated)
const cardNumber = ref('')
const cardHolder = ref('')
const expDate = ref('')
const cvv = ref('')

const invoice = ref(null)
const isTrial = ref(false)

// Front-end payment simulation
const proceedWithPayment = () => {
  if (selectedRadio.value === 'paypal') {
    isPaypalModalVisible.value = true
  } else {
    isCreditCardModalVisible.value = true
  }
}

const submitCreditCardPayment = () => {
  alert(`Payment successful!\nAmount: $${total.toFixed(2)}\nMethod: Credit Card (Simulated)\nWe do NOT store your credit card info.`)
  isCreditCardModalVisible.value = false
}

const submitPaypalPayment = () => {
  alert(`Payment successful!\nAmount: $${total.toFixed(2)}\nMethod: PayPal (Simulated)\nAutomatic payments enabled if you keep PayPal saved.`)
  isPaypalModalVisible.value = false
}

const amount = ref(0);
const currency = ref("EUR");


const payNow = async (planId) => {
  try {
    const res = await $api('https://api-ds.bitemybytes.com/api/v1/paypal/payment', {
      method: 'POST',
      body: {
        amount: subscriptionPrice.value,
        currency: 'EUR',
        invoice_id: route.params.id
      },
      onResponseError({ response }) {
        //$toast.error('An error occurred while processing the payment.');
      },
    })
    // Access invoice ID correctly
    if (res.approval_url) {
      window.location.href = res.approval_url;
    } else {
      console.error(res);
    }
    
  } catch (err) {
    console.error('Error procing the payment:', err)
    //$toast.error('The payment has not been processed.')
  }
}


onMounted(async () => {
  try {
    const data = await $api(`https://api-ds.bitemybytes.com/api/v1/client/invoice/${route.params.id}`, {
        method: 'GET',
    })

    invoice.value = data
    isTrial.value = data.is_trial

    //console.log("GENT", data.amount)
    servicePrice.value = parseFloat(data.amount)

    if (isTrial.value) {
      selectedRadio.value = 'credit card'
    }
  } catch (err) {
    if (err.response?.status === 404) {
      // Redirect if invoice not found
      router.replace('/client/plans-billing')
    } else {
      console.error(err)
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
            <!-- Billing Form -->
            <VCol cols="12" md="8" :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'">
              <VCardText class="pa-8 pe-5">
                <div>
                  <h4 class="text-h4 mb-2">Checkout</h4>
                  <div class="text-body-1">
                    All plans include 40+ advanced tools and features. Choose the best plan.
                  </div>
                </div>

                <!-- Payment Method -->
                <CustomRadios
                  v-model:selected-radio="selectedRadio"
                  :radio-content="radioContent"
                  :grid-column="{ cols: '12', sm: '4' }"
                  class="my-8"
                  :disabled="isTrial"
                >
                  <template #default="{ item }">
                    <div class="d-flex align-center gap-x-4 ms-3">
                      <VIcon
                        size="24"
                        :icon='item.images'
                        
                      />
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
                  Note: We do <strong>NOT</strong> store your credit card information. You will be asked to enter it for each payment. Automatic payments only apply if you use PayPal and keep it linked.
                </div>
              </VCardText>
            </VCol>

            <!-- Order Summary -->
            <VCol cols="12" md="4">
              <VCardText class="pa-8 ps-5">
                <div class="mb-8">
                  <h4 class="text-h4 mb-2">Order Summary</h4>
                  <div class="text-body-1">Review your subscription and total before payment.</div>
                </div>

                <VCard flat color="rgba(var(--v-theme-on-surface), var(--v-hover-opacity))">
                  <VCardText>
                    <div class="text-body-1">A simple start for everyone</div>
                    <h1 class="text-h1 my-4">${{ subscriptionPrice }}
                    <!--<span class="text-body-1 font-weight-medium">/month</span>-->
                    </h1>
                    <RouterLink :to='"/client/invoice/change-plan/"+route.params.id'>
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

                <WiseCardDialog
                  v-model="isDialogVisible"
                 />

                <VBtn block color="success" class="mb-8" @click="payNow">
                  <template #append>
                    <VIcon icon="tabler-arrow-right" class="flip-in-rtl" />
                  </template>
                  Proceed With Payment
                </VBtn>

              </VCardText>
            </VCol>
          </VRow>
        </VCard>
      </div>
    </VContainer>


  </div>
</template>

<style lang="scss" scoped>
.payment-card { margin-block: 10.5rem 5.25rem; }
.payment-page { @media (min-width: 600px) and (max-width: 960px) { .v-container { padding-inline: 2rem !important; } } }
.payment-card .custom-radio .v-radio { margin-block-start: 0 !important; }
.payment-card {
  margin-block: 0!important;
}
</style>
