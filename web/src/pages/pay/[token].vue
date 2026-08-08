<script setup>
import { loadStripe } from '@/utils/stripe'

definePage({
  meta: { layout: 'blank', public: true },
})

const route = useRoute('pay-token')

const invoice = ref(null)
const notFound = ref(false)
const working = ref(false)
const errorMessage = ref('')
const rail = ref('card')

let stripe = null
let elements = null
const cardReady = ref(false)

const load = async () => {
  try {
    invoice.value = await $api(`/v1/public/invoices/${route.params.token}`)
  } catch (err) {
    console.error('Failed to load the pay link:', err)
    notFound.value = true
  }
}

const mountCard = async () => {
  errorMessage.value = ''
  try {
    const [stripeInstance, res] = await Promise.all([
      loadStripe(),
      $api(`/v1/public/invoices/${route.params.token}/pay`, { method: 'POST' }),
    ])

    if (!stripeInstance) {
      errorMessage.value = 'Card payments are not available right now.'

      return
    }

    stripe = stripeInstance
    elements = stripe.elements({ clientSecret: res.client_secret })
    await nextTick()
    elements.create('payment').mount('#public-payment-element')
    cardReady.value = true
  } catch (err) {
    errorMessage.value = err.data?.message ?? 'Could not start the payment.'
  }
}

const pay = async () => {
  if (!stripe || !elements) return
  working.value = true
  errorMessage.value = ''

  const { error } = await stripe.confirmPayment({
    elements,
    confirmParams: {
      return_url: `${window.location.origin}/pay/${route.params.token}`,
    },
  })

  if (error) {
    errorMessage.value = error.message
    working.value = false
  }
}

watch(rail, selected => {
  if (selected === 'card' && !cardReady.value && invoice.value?.payable) mountCard()
})

onMounted(async () => {
  await load()
  if (invoice.value?.payable) await mountCard()
})
</script>

<template>
  <VContainer class="py-10" style="max-inline-size: 56rem;">
    <div class="d-flex justify-center mb-8">
      <AppLogo />
    </div>

    <VAlert v-if="notFound" type="error" variant="tonal">
      This payment link is invalid or has expired. Please ask for a new one.
    </VAlert>

    <VRow v-else-if="invoice">
      <!-- Invoice summary -->
      <VCol cols="12" md="6">
        <VCard variant="outlined">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h6 class="text-h6">{{ invoice.invoice_number }}</h6>
              <VChip v-if="!invoice.payable" size="small" color="success" label>Settled</VChip>
            </div>

            <div v-for="line in invoice.lines" :key="line.description" class="d-flex justify-space-between mb-2">
              <span>{{ line.description }} × {{ line.quantity }}</span>
              <span>{{ formatMoney(line.line_total_gross) }}</span>
            </div>
            <VDivider class="my-3" />
            <div class="d-flex justify-space-between font-weight-medium mb-1">
              <span>Amount due</span>
              <span>{{ formatMoney(invoice.amount_due) }}</span>
            </div>
            <div v-if="invoice.due_at" class="d-flex justify-space-between text-body-2">
              <span>Due date</span>
              <span>{{ formatDate(invoice.due_at) }}</span>
            </div>

            <VDivider class="my-3" />
            <div class="text-body-2">
              {{ invoice.seller.name }}<br>
              {{ invoice.seller.address }}<br>
              <template v-if="invoice.seller.uid">{{ invoice.seller.uid }}<br></template>
              {{ invoice.seller.email }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Payment -->
      <VCol cols="12" md="6">
        <template v-if="invoice.payable">
          <VRadioGroup v-model="rail" inline class="mb-4">
            <VRadio value="card" label="Card" />
            <VRadio value="transfer" label="Bank transfer" />
          </VRadioGroup>

          <VAlert v-if="errorMessage" type="error" variant="tonal" density="compact" class="mb-4">
            {{ errorMessage }}
          </VAlert>

          <template v-if="rail === 'card'">
            <VCard variant="outlined" class="mb-4">
              <VCardText>
                <div id="public-payment-element" />
                <VProgressCircular v-if="!cardReady && !errorMessage" indeterminate class="d-block mx-auto my-4" />
              </VCardText>
            </VCard>
            <VBtn block color="primary" :loading="working" :disabled="!cardReady" @click="pay">
              Pay {{ formatMoney(invoice.amount_due) }}
            </VBtn>
          </template>

          <VCard v-else-if="invoice.bank" variant="outlined">
            <VCardText>
              <div class="mb-2"><span class="text-body-2">IBAN</span><div class="font-weight-medium">{{ invoice.bank.iban }}</div></div>
              <div class="mb-2"><span class="text-body-2">BIC</span><div class="font-weight-medium">{{ invoice.bank.bic }}</div></div>
              <div class="mb-3"><span class="text-body-2">Reference</span><div class="font-weight-medium">{{ invoice.bank.reference }}</div></div>
              <div v-if="invoice.bank.epc_qr_png" class="text-center">
                <img :src="invoice.bank.epc_qr_png" alt="EPC QR — scan with your banking app" width="160" height="160">
                <div class="text-body-2 mt-1">Scan with your banking app</div>
              </div>
            </VCardText>
          </VCard>
        </template>

        <VAlert v-else type="success" variant="tonal">
          This invoice is settled — nothing left to pay.
        </VAlert>
      </VCol>
    </VRow>
  </VContainer>
</template>
