<script setup>
const { t } = useI18n()
const invoices = ref([])
const total = ref(0)
const loading = ref(false)

const page = ref(1)
const perPage = ref(10)
const search = ref('')

// Filter drawer — per-status show/hide with colour dots, from the mock.
const filterDrawer = ref(false)
const allStatuses = [
  { value: 'draft', label: 'Draft', color: 'secondary' },
  { value: 'sent', label: 'Open', color: 'info' },
  { value: 'awaiting_confirmation', label: 'In review', color: 'warning' },
  { value: 'paid', label: 'Paid', color: 'success' },
  { value: 'cancelled', label: 'Cancelled', color: 'secondary' },
]
const visibleStatuses = ref([])
const activeFilterCount = computed(() => visibleStatuses.value.length)

const headers = computed(() => [
  { title: t('common.nr'), key: 'invoice_number' },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: 'Billed to', key: 'billed_to', sortable: false },
  { title: 'Manager', key: 'account_manager', sortable: false },
  { title: 'Issued', key: 'issued_at' },
  { title: 'Due', key: 'due_at' },
  { title: t('common.amount'), key: 'total_gross', align: 'end' },
  { title: 'Paid', key: 'amount_paid', align: 'end' },
  { title: t('dashboard.outstanding'), key: 'amount_due', align: 'end' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
])

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/admin/billing/invoices', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        'statuses[]': visibleStatuses.value.length ? visibleStatuses.value : undefined,
      },
    })

    invoices.value = res.data
    total.value = res.meta.total
  } catch (err) {
    console.error('Failed to load invoices:', err)
  } finally {
    loading.value = false
  }
}

watch([page, perPage], load)
onMounted(load)

let searchTimeout
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    load()
  }, 400)
})

const applyFilters = () => {
  filterDrawer.value = false
  page.value = 1
  load()
}

// Row actions
const snackbar = ref({ show: false, text: '', color: 'success' })

const notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const rowAction = async (invoice, action) => {
  try {
    if (action === 'cancel') {
      await $api(`/v1/admin/billing/invoices/${invoice.id}/cancel`, { method: 'POST' })
      notify(`${invoice.invoice_number} cancelled.`)
    }

    if (action === 'credit-note') {
      const res = await $api(`/v1/admin/billing/invoices/${invoice.id}/credit-note`, { method: 'POST', body: {} })

      notify(`Credit note ${res.data.invoice_number} issued.`)
    }

    if (action === 'issue') {
      const res = await $api(`/v1/admin/billing/invoices/${invoice.id}/issue`, { method: 'POST', body: {} })

      notify(`Issued as ${res.data.invoice_number}.`)
    }

    await load()
  } catch (err) {
    console.error(`Invoice action ${action} failed:`, err)
    notify(err.data?.message ?? 'The action failed.', 'error')
  }
}
</script>

<template>
  <section>
    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('nav.invoices') }}</VCardTitle>
        <VCardSubtitle>{{ $t('adminInvoices.subtitle') }}</VCardSubtitle>
        <template #append>
          <VBtn color="primary" prepend-icon="tabler-plus" :to="{ name: 'admin-invoices-add-invoice' }">
            {{ $t('adminInvoices.new') }}
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="d-flex flex-wrap gap-4">
        <VBtn variant="tonal" color="secondary" prepend-icon="tabler-filter" @click="filterDrawer = true">
          {{ $t('common.filter') }}
          <VBadge
            v-if="activeFilterCount"
            :content="activeFilterCount"
            color="primary"
            inline
            class="ms-1"
          />
        </VBtn>
        <VSpacer />
        <VTextField
          v-model="search"
          density="compact"
          prepend-inner-icon="tabler-search"
          :placeholder="$t('adminInvoices.search')"
          style="max-inline-size: 20rem;"
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
            :to="{ name: 'admin-invoices-id', params: { id: item.id } }"
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

        <template #item.billed_to="{ item }">
          {{ item.billed_to?.name ?? '—' }}
        </template>

        <template #item.account_manager="{ item }">
          {{ item.account_manager ?? '—' }}
        </template>

        <template #item.issued_at="{ item }">
          {{ formatDate(item.issued_at) }}
        </template>

        <template #item.due_at="{ item }">
          {{ formatDate(item.due_at) }}
        </template>

        <template #item.total_gross="{ item }">
          {{ formatMoney(item.total_gross) }}
        </template>

        <template #item.amount_paid="{ item }">
          {{ formatMoney(item.amount_paid) }}
        </template>

        <template #item.amount_due="{ item }">
          {{ formatMoney(item.amount_due) }}
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-eye"
            size="small"
            variant="text"
            :to="{ name: 'admin-invoices-id', params: { id: item.id } }"
          />
          <VMenu>
            <template #activator="{ props }">
              <VBtn icon="tabler-dots-vertical" size="small" variant="text" v-bind="props" />
            </template>
            <VList density="compact">
              <VListItem v-if="item.status === 'draft'" @click="rowAction(item, 'issue')">
                <VListItemTitle>{{ $t('adminInvoices.issue') }}</VListItemTitle>
              </VListItem>
              <!-- Legal retention: no delete. Drafts cancel, issued get a credit note. -->
              <VListItem v-if="item.status === 'draft'" @click="rowAction(item, 'cancel')">
                <VListItemTitle>{{ $t('common.cancel') }}</VListItemTitle>
              </VListItem>
              <VListItem
                v-if="['sent', 'awaiting_confirmation', 'paid'].includes(item.status)"
                @click="rowAction(item, 'credit-note')"
              >
                <VListItemTitle>{{ $t('adminInvoices.creditNote') }}</VListItemTitle>
              </VListItem>
              <VListItem :to="{ name: 'admin-invoices-id', params: { id: item.id }, query: { action: 'payment' } }">
                <VListItemTitle>{{ $t('adminInvoices.manualPayment') }}</VListItemTitle>
              </VListItem>
              <VListItem :to="{ name: 'admin-invoices-id', params: { id: item.id }, query: { action: 'reminder' } }">
                <VListItemTitle>{{ $t('adminInvoices.sendReminder') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Filter drawer -->
    <VNavigationDrawer
      v-model="filterDrawer"
      temporary
      location="end"
      width="320"
    >
      <div class="pa-6">
        <div class="d-flex justify-space-between align-center mb-6">
          <h6 class="text-h6">{{ $t('adminInvoices.filterTitle') }}</h6>
          <VBtn icon="tabler-x" variant="text" size="small" @click="filterDrawer = false" />
        </div>

        <div class="text-body-2 mb-3">{{ $t('adminInvoices.filterHint') }}</div>
        <VCheckbox
          v-for="status in allStatuses"
          :key="status.value"
          v-model="visibleStatuses"
          :value="status.value"
          density="compact"
          hide-details
          class="mb-1"
        >
          <template #label>
            <span class="me-2">{{ status.label }}</span>
            <VIcon icon="tabler-circle-filled" size="10" :color="status.color" />
          </template>
        </VCheckbox>

        <div class="d-flex gap-3 mt-8">
          <VBtn variant="tonal" color="secondary" block @click="visibleStatuses = []; applyFilters()">
            {{ $t('common.reset') }}
          </VBtn>
          <VBtn color="primary" block @click="applyFilters">
            {{ $t('common.apply') }}
          </VBtn>
        </div>
      </div>
    </VNavigationDrawer>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>
