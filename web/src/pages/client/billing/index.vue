<script setup>
const billingApi = useBillingApi()

const summary = ref(null)
const invoices = ref([])
const total = ref(0)
const loading = ref(false)

const page = ref(1)
const perPage = ref(10)
const search = ref('')
const statusFilter = ref('')

const statusOptions = [
  { title: 'All statuses', value: '' },
  { title: 'Open', value: 'sent' },
  { title: 'In review', value: 'awaiting_confirmation' },
  { title: 'Paid', value: 'paid' },
  { title: 'Cancelled', value: 'cancelled' },
]

const headers = [
  { title: 'NR', key: 'invoice_number' },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Type', key: 'type', sortable: false },
  { title: 'Due date', key: 'due_at' },
  { title: 'Amount', key: 'total_gross', align: 'end' },
  { title: 'Open', key: 'amount_due', align: 'end' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

const loadSummary = async () => {
  try {
    summary.value = await billingApi.summary()
  } catch (err) {
    console.error('Failed to load billing summary:', err)
  }
}

const loadInvoices = async () => {
  loading.value = true
  try {
    const res = await billingApi.invoices({
      page: page.value,
      perPage: perPage.value,
      status: statusFilter.value,
      search: search.value,
    })

    invoices.value = res.data
    total.value = res.meta.total
  } catch (err) {
    console.error('Failed to load invoices:', err)
  } finally {
    loading.value = false
  }
}

watch([page, perPage, statusFilter], loadInvoices)

let searchTimeout
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    loadInvoices()
  }, 400)
})

onMounted(() => {
  loadSummary()
  loadInvoices()
})
</script>

<template>
  <section>
    <!-- KPI strip -->
    <VCard class="mb-6">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div class="d-flex flex-wrap gap-6">
          <div class="d-flex align-center gap-3">
            <VAvatar variant="tonal" color="primary" rounded>
              <VIcon icon="tabler-file-invoice" />
            </VAvatar>
            <div>
              <div class="text-body-2">{{ $t('billing.lastInvoice') }}</div>
              <h6 class="text-h6">{{ formatMoney(summary?.last_invoice_total) }}</h6>
            </div>
          </div>

          <div class="d-flex align-center gap-3">
            <VAvatar variant="tonal" color="primary" rounded>
              <VIcon icon="tabler-calendar-due" />
            </VAvatar>
            <div>
              <div class="text-body-2">{{ $t('billing.nextDueDate') }}</div>
              <h6 class="text-h6">{{ formatDate(summary?.next_due_at) }}</h6>
            </div>
          </div>

          <div class="d-flex align-center gap-3">
            <VAvatar variant="tonal" color="primary" rounded>
              <VIcon icon="tabler-wallet" />
            </VAvatar>
            <div>
              <div class="text-body-2">{{ $t('dashboard.outstanding') }}</div>
              <h6 class="text-h6">{{ formatMoney(summary?.outstanding_balance) }}</h6>
            </div>
          </div>
        </div>

        <VBtn
          color="primary"
          append-icon="tabler-chevron-right"
          :disabled="!summary?.open_invoices"
          :to="{ name: 'client-billing-pay' }"
        >
          {{ $t('billing.payOnline') }}
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Invoice list -->
    <VCard>
      <VCardText class="d-flex flex-wrap gap-4">
        <VSelect
          v-model="statusFilter"
          :items="statusOptions"
          density="compact"
          style="max-inline-size: 12rem;"
          :label="$t('common.filter')"
        />
        <VSpacer />
        <VTextField
          v-model="search"
          density="compact"
          prepend-inner-icon="tabler-search"
          :placeholder="$t('billing.searchInvoices')"
          style="max-inline-size: 16rem;"
        />
      </VCardText>

      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="invoices"
        :items-length="total"
        :loading="loading"
        class="text-no-wrap"
      >
        <template #item.invoice_number="{ item }">
          <RouterLink
            :to="{ name: 'client-billing-invoices-id', params: { id: item.id } }"
            class="font-weight-medium text-link"
          >
            {{ item.invoice_number }}
          </RouterLink>
        </template>

        <template #item.status="{ item }">
          <VChip v-bind="invoiceStatusChip(item)" size="small" label>
            {{ invoiceStatusChip(item).label }}
          </VChip>
        </template>

        <template #item.type="{ item }">
          {{ invoiceTypeLabel(item.type) }}
        </template>

        <template #item.due_at="{ item }">
          {{ formatDate(item.due_at) }}
        </template>

        <template #item.total_gross="{ item }">
          {{ formatMoney(item.total_gross) }}
        </template>

        <template #item.amount_due="{ item }">
          {{ formatMoney(item.amount_due) }}
        </template>

        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            color="secondary"
            class="me-2"
            :to="{ name: 'client-billing-invoices-id', params: { id: item.id } }"
          >
            {{ $t('common.view') }}
          </VBtn>
          <VBtn
            v-if="item.amount_due > 0 && ['sent', 'awaiting_confirmation', 'unpaid'].includes(item.status)"
            size="small"
            color="primary"
            :to="{ name: 'client-billing-checkout-id', params: { id: item.id } }"
          >
            {{ $t('billing.pay') }}
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </section>
</template>
