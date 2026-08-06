<script setup>

import BillingHistory from '@/views/client/billing-plans/BillingHistory.vue'

import americanExpress from '@images/icons/payments/american-express.png'
import mastercard from '@images/icons/payments/mastercard.png'

import paypalDark from '@images/icons/payments/img/paypal-dark.png'
import paypalLight from '@images/icons/payments/img/paypal-light.png'
import visaDark from '@images/icons/payments/img/visa-dark.png'
import visaLight from '@images/icons/payments/img/visa-light.png'


const visa = useGenerateImageVariant(visaLight, visaDark)
const paypal = useGenerateImageVariant(paypalLight, paypalDark)

const radioContent = [
  { title: 'Credit Card', value: 'credit card', images: visa.value },
  { title: 'PayPal', value: 'paypal', images: paypal.value }
]

const selectedRadio = ref('credit card')

const isUpgradePlanDialogVisible = ref(false)
const currentCardDetails = ref()
const isCardEditDialogVisible = ref(false)
const isCardAddDialogVisible = ref(false)
const isEditAddressDialogVisible = ref(false)

const openEditCardDialog = cardDetails => {
  currentCardDetails.value = cardDetails
  isCardEditDialogVisible.value = true
}

const paypalSaved = ref(true);

const creditCards = [
  {
    name: 'Tom McBride',
    number: '4851234567899865',
    expiry: '12/24',
    isPrimary: true,
    isExpired: false,
    type: 'mastercard',
    cvv: '123',
    image: mastercard,
  },
  {
    name: 'Mildred Wagner',
    number: '5531234567895678',
    expiry: '02/24',
    isPrimary: false,
    isExpired: false,
    type: 'visa',
    cvv: '456',
    image: visa,
  },
  {
    name: 'Lester Jennings',
    number: '5531234567890002',
    expiry: '08/20',
    isPrimary: false,
    isExpired: true,
    type: 'visa',
    cvv: '456',
    image: americanExpress,
  },
]

const currentAddress = {
  companyName: 'Pixinvent',
  billingEmail: 'gertrude@gmail.com',
  taxID: 'TAX-875623',
  vatNumber: 'SDF754K77',
  address: '100 Water Plant Avenue, Building 1303 Wake Island',
  contact: '+1(609) 933-44-22',
  country: 'USA',
  state: 'Queensland',
  zipCode: 403114,
}

const currentBillingAddress = {
  firstName: 'Shamus',
  lastName: 'Tuttle',
  selectedCountry: 'USA',
  addressLine1: '45 Rocker Terrace',
  addressLine2: 'Latheronwheel',
  landmark: 'KW5 8NW, London',
  contact: '+1 (609) 972-22-22',
  country: 'USA',
  city: 'London',
  state: 'London',
  zipCode: 110001,
}
</script>

<template>
  <VRow>
    <!-- 👉 Current Plan -->
    <VCol cols="6">
      <VCard title="Current Plan">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
              order-md="1"
              order="2"
            >
              <h6 class="text-h6 mb-1">
                Your Current Plan is Basic
              </h6>
              <p>
                A simple start for everyone
              </p>

              <h6 class="text-h6 mb-1">
                Active until Dec 09, 2021
              </h6>
              <p>
                We will send you a notification upon Subscription expiration
              </p>

              <h6 class="text-h6 mb-1">
                <span class="d-inline-block me-2">$99 Per Month</span>
                <VChip
                  color="primary"
                  size="small"
                  label
                >
                  Popular
                </VChip>
              </h6>
              <p class="mb-0">
                Standard plan for small to medium businesses
              </p>
            </VCol>

            

            <VCol
              cols="12"
              order="3"
            >
              <div class="d-flex flex-wrap gap-4">
                <VBtn @click="isUpgradePlanDialogVisible = true">
                  upgrade plan
                </VBtn>

                <VBtn
                  color="error"
                  variant="tonal"
                >
                  Cancel Subscription
                </VBtn>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Payment Methods -->
    <VCol cols="6">
      <VCard width="100%"
      title="Billing Payment Method">
          <VRow>
            <VCol cols="12" md="12" :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'">
              <VCardText class="pa-8 pe-5">
                <div>
                  <div class="text-body-1">
                    This payment method will be used for your current purchase. Only PayPal supports automatic renewals. Credit cards are charged per transaction.
                  </div>
                </div>

                <!-- Payment Method -->
                <CustomRadios
                  v-model:selected-radio="selectedRadio"
                  :radio-content="radioContent"
                  :grid-column="{ cols: '12', sm: '6' }"
                  class="my-8"
                >
                  <template #default="{ item }">
                    <div class="d-flex align-center gap-x-4 ms-3">
                      <img :src="item.images" height="34" />
                      <h6 class="text-h6">{{ item.title }}</h6>
                    </div>
                  </template>
                </CustomRadios>


                <div
                v-if="selectedRadio === 'paypal'"
                class="mt-6 pa-4 rounded-lg border"
                >
                <div class="d-flex align-center" style="justify-content: space-between;">
                    <!-- STATUS -->
                    <div>
                    <div
                        v-if="paypalSaved"
                        class="d-flex align-center gap-2 text-sm mb-1"
                    >
                        <VIcon size="16" icon="tabler-check" />
                        <span>PayPal linked · Auto-renewal enabled</span>
                    </div>

                    <div
                        v-else
                        class="text-sm text-medium-emphasis"
                    >
                        PayPal will be used for a one-time payment.
                    </div>
                    </div>

                    <!-- ACTION -->
                    <VBtn
                    v-if="paypalSaved"
                    color="error"
                    class="ml-2"
                    >
                    Cancel auto-renewal
                    </VBtn>
                </div>

                <!-- HELPER TEXT -->
                <div class="mt-2 text-xs text-medium-emphasis">
                    You can cancel PayPal auto-renewal at any time. Your current plan will remain
                    active until the end of the billing period.
                </div>
                </div>

                 

                

                <div class="mt-4 text-sm text-gray-600">
                Note: We do not store your credit card information.<br>
PayPal payments may be linked to your account and used for automatic renewals until you cancel.
                </div>
              </VCardText>
            </VCol>

          </VRow>
        </VCard>
    </VCol>

    <VCol cols="12">
      <BillingHistory />
    </VCol>
  </VRow>

</template>

<style lang="scss">
.billing-address-table {
  tr {
    td:first-child {
      inline-size: 148px;
    }
  }
}
</style>
