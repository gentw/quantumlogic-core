<script setup>
const orders = ref([])
const total = ref(0)
const loading = ref(false)

const page = ref(1)
const perPage = ref(10)
const status = ref('')
const search = ref('')

const headers = [
  { title: 'Order', key: 'order_number' },
  { title: 'Client', key: 'client', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created', key: 'created_at' },
  { title: 'Total', key: 'total_gross', align: 'end' },
]

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/admin/billing/orders', {
      query: {
        page: page.value,
        per_page: perPage.value,
        status: status.value || undefined,
        search: search.value || undefined,
      },
    })

    orders.value = res.data
    total.value = res.meta.total
  } catch (err) {
    console.error('Failed to load orders:', err)
  } finally {
    loading.value = false
  }
}

watch([page, perPage, status], load)
onMounted(load)

let searchTimeout
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    load()
  }, 400)
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Service orders</VCardTitle>
    </VCardItem>

    <VCardText class="d-flex flex-wrap gap-4">
      <VSelect
        v-model="status"
        :items="[
          { title: 'All statuses', value: '' },
          { title: 'Draft', value: 'draft' },
          { title: 'Awaiting payment', value: 'awaiting_payment' },
          { title: 'Active', value: 'active' },
          { title: 'In delivery', value: 'in_delivery' },
          { title: 'Completed', value: 'completed' },
          { title: 'Cancelled', value: 'cancelled' },
        ]"
        density="compact"
        style="max-inline-size: 13rem;"
        label="Status"
      />
      <VSpacer />
      <VTextField
        v-model="search"
        density="compact"
        prepend-inner-icon="tabler-search"
        placeholder="Order number or client"
        style="max-inline-size: 16rem;"
      />
    </VCardText>

    <VDataTableServer
      v-model:page="page"
      v-model:items-per-page="perPage"
      :headers="headers"
      :items="orders"
      :items-length="total"
      :loading="loading"
      class="text-no-wrap"
    >
      <template #item.order_number="{ item }">
        <RouterLink
          :to="{ name: 'admin-orders-id', params: { id: item.id } }"
          class="font-weight-medium text-link"
        >
          {{ item.order_number }}
        </RouterLink>
      </template>

      <template #item.client="{ item }">
        {{ item.client?.name ?? '—' }}
      </template>

      <template #item.status="{ item }">
        <VChip size="small" label>
          {{ item.status.replaceAll('_', ' ') }}
        </VChip>
      </template>

      <template #item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>

      <template #item.total_gross="{ item }">
        {{ formatMoney(item.total_gross) }}
      </template>
    </VDataTableServer>
  </VCard>
</template>
