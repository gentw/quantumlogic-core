<script setup>
const router = useRouter()
const billingApi = useBillingApi()

const summary = ref(null)
const openInvoices = ref([])
const mode = ref('latest')
const selectedInvoiceId = ref(null)
const customAmount = ref(null)
const errorMessage = ref('')

const userData = useCookie('userData').value

// Oldest open invoice first — custom amounts and "pay everything" start there.
const oldestOpen = computed(() => openInvoices.value[openInvoices.value.length - 1])
const latestOpen = computed(() => openInvoices.value[0])

const load = async () => {
  try {
    summary.value = await billingApi.summary()

    const res = await billingApi.invoices({ status: 'sent', perPage: 50 })

    openInvoices.value = res.data.filter(invoice => invoice.amount_due > 0)
    selectedInvoiceId.value = openInvoices.value[0]?.id ?? null
  } catch (err) {
    console.error('Failed to load open invoices:', err)
    errorMessage.value = 'Your open invoices could not be loaded.'
  }
}

const proceed = () => {
  errorMessage.value = ''

  if (mode.value === 'latest' && latestOpen.value)
    return router.push({ name: 'client-billing-checkout-id', params: { id: latestOpen.value.id } })

  if (mode.value === 'selected' && selectedInvoiceId.value)
    return router.push({ name: 'client-billing-checkout-id', params: { id: selectedInvoiceId.value } })

  if (mode.value === 'custom' && oldestOpen.value) {
    const amount = Number(customAmount.value)

    if (!amount || amount <= 0) {
      errorMessage.value = 'Enter the amount you want to pay.'

      return
    }

    // A custom amount always settles the oldest debt first.
    return router.push({
      name: 'client-billing-checkout-id',
      params: { id: oldestOpen.value.id },
      query: { amount: Math.min(amount, oldestOpen.value.amount_due) },
    })
  }

  if (mode.value === 'all' && oldestOpen.value)
    return router.push({ name: 'client-billing-checkout-id', params: { id: oldestOpen.value.id } })

  errorMessage.value = 'There is nothing open to pay.'
}

onMounted(load)
</script>

<template>
  <VCard title="Online payment">
    <VCardText>
      <VRow>
        <!-- Client details -->
        <VCol cols="12" md="5">
          <h6 class="text-h6 mb-4">Your details</h6>
          <VTable density="comfortable" class="border rounded">
            <tbody>
              <tr>
                <td class="text-body-2">Name</td>
                <td class="font-weight-medium">{{ userData?.name }} {{ userData?.surname }}</td>
              </tr>
              <tr>
                <td class="text-body-2">Last invoice</td>
                <td class="font-weight-medium">{{ formatMoney(summary?.last_invoice_total) }}</td>
              </tr>
              <tr>
                <td class="text-body-2">Outstanding balance</td>
                <td class="font-weight-medium">{{ formatMoney(summary?.outstanding_balance) }}</td>
              </tr>
              <tr>
                <td class="text-body-2">Open invoices</td>
                <td class="font-weight-medium">{{ summary?.open_invoices ?? 0 }}</td>
              </tr>
            </tbody>
          </VTable>

          <p class="text-body-2 mt-4 mb-0">
            Pay your balance online by card, PayPal or bank transfer.
          </p>
        </VCol>

        <VDivider vertical class="d-none d-md-block" />

        <!-- Payment options -->
        <VCol cols="12" md="6">
          <h6 class="text-h6 mb-4">Payment options</h6>

          <VRadioGroup v-model="mode">
            <VRadio value="latest" label="Pay the latest invoice" class="mb-1" />

            <VRadio value="selected" label="Pay a selected invoice" class="mb-1" />
            <VSelect
              v-if="mode === 'selected'"
              v-model="selectedInvoiceId"
              :items="openInvoices"
              :item-title="invoice => `${invoice.invoice_number} — ${formatMoney(invoice.amount_due)}`"
              item-value="id"
              density="compact"
              class="mb-3 ms-8"
              label="NR"
            />

            <VRadio value="custom" label="Pay a custom amount" class="mb-1" />
            <VTextField
              v-if="mode === 'custom'"
              v-model="customAmount"
              type="number"
              min="1"
              density="compact"
              class="mb-3 ms-8"
              label="Amount"
              suffix="EUR"
            />

            <VRadio value="all" label="Pay everything" />
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

          <VBtn
            block
            color="primary"
            :disabled="!openInvoices.length"
            @click="proceed"
          >
            Pay now
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
