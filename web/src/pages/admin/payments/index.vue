<script setup>
const { t } = useI18n()
const payments = ref([])
const total = ref(0)
const loading = ref(false)

const page = ref(1)
const perPage = ref(15)
const provider = ref('')
const status = ref('')
const search = ref('')

const headers = computed(() => [
  { title: t('nav.invoices'), key: 'invoice_number' },
  { title: t('common.client'), key: 'client', sortable: false },
  { title: t('payments.provider'), key: 'provider', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('payments.method'), key: 'method', sortable: false },
  { title: t('common.date'), key: 'paid_at' },
  { title: t('common.amount'), key: 'amount', align: 'end' },
])

const statusColor = {
  pending: 'secondary',
  processing: 'info',
  awaiting_confirmation: 'warning',
  succeeded: 'success',
  failed: 'error',
  refunded: 'secondary',
  partially_refunded: 'warning',
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/admin/billing/payments', {
      query: {
        page: page.value,
        per_page: perPage.value,
        provider: provider.value || undefined,
        status: status.value || undefined,
        search: search.value || undefined,
      },
    })

    payments.value = res.data
    total.value = res.total
  } catch (err) {
    console.error('Failed to load payments:', err)
  } finally {
    loading.value = false
  }
}

watch([page, perPage, provider, status], load)
onMounted(load)

let searchTimeout
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    load()
  }, 400)
})

/** Client-side CSV of the current page — enough for the accountant handoff. */
const exportCsv = () => {
  const rows = [
    ['Invoice', 'Client', 'Provider', 'Status', 'Method', 'Date', 'Amount', 'Refunded'],
    ...payments.value.map(p => [
      p.invoice_number, p.client, p.provider, p.status, p.method ?? '', p.paid_at ?? '', p.amount, p.refunded_amount,
    ]),
  ]

  const csv = rows.map(row => row.map(cell => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(';')).join('\n')
  const link = document.createElement('a')

  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  link.download = `payments-page-${page.value}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('nav.payments') }}</VCardTitle>
      <VCardSubtitle>{{ $t('payments.subtitle') }}</VCardSubtitle>
      <template #append>
        <VBtn variant="tonal" color="secondary" prepend-icon="tabler-download" @click="exportCsv">
          {{ $t('payments.exportCsv') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="d-flex flex-wrap gap-4">
      <VSelect
        v-model="provider"
        :items="[
          { title: 'All providers', value: '' },
          { title: 'Stripe', value: 'stripe' },
          { title: 'PayPal', value: 'paypal' },
          { title: 'Bank transfer', value: 'bank_transfer' },
          { title: 'Manual', value: 'manual' },
        ]"
        density="compact"
        style="max-inline-size: 11rem;"
        :label="$t('payments.provider')"
      />
      <VSelect
        v-model="status"
        :items="[
          { title: 'All statuses', value: '' },
          { title: 'Succeeded', value: 'succeeded' },
          { title: 'Awaiting confirmation', value: 'awaiting_confirmation' },
          { title: 'Pending', value: 'pending' },
          { title: 'Failed', value: 'failed' },
          { title: 'Refunded', value: 'refunded' },
        ]"
        density="compact"
        style="max-inline-size: 13rem;"
        :label="$t('common.status')"
      />
      <VSpacer />
      <VTextField
        v-model="search"
        density="compact"
        prepend-inner-icon="tabler-search"
        :placeholder="$t('payments.search')"
        style="max-inline-size: 18rem;"
      />
    </VCardText>

    <VDataTableServer
      v-model:page="page"
      v-model:items-per-page="perPage"
      :headers="headers"
      :items="payments"
      :items-length="total"
      :loading="loading"
      class="text-no-wrap"
    >
      <template #item.invoice_number="{ item }">
        <RouterLink
          v-if="item.invoice_id"
          :to="{ name: 'admin-invoices-id', params: { id: item.invoice_id } }"
          class="font-weight-medium text-link"
        >
          {{ item.invoice_number }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template #item.provider="{ item }">
        <span class="text-capitalize">{{ item.provider.replace('_', ' ') }}</span>
      </template>

      <template #item.status="{ item }">
        <VChip size="small" label :color="statusColor[item.status] ?? 'secondary'">
          {{ item.status.replaceAll('_', ' ') }}
        </VChip>
      </template>

      <template #item.method="{ item }">
        {{ item.method ?? '—' }}
      </template>

      <template #item.paid_at="{ item }">
        {{ item.paid_at ?? '—' }}
      </template>

      <template #item.amount="{ item }">
        {{ formatMoney(item.amount) }}
        <span v-if="item.refunded_amount > 0" class="text-error text-body-2">
          (−{{ formatMoney(item.refunded_amount) }})
        </span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
