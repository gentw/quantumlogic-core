<script setup>

const searchQuery = ref('')
const selectedStatus = ref()
const selectedRows = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// 👉 headers

// Headers remain the same
const headers = [
  { title: '#', key: 'id' },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Total', key: 'total' },
  { title: 'Issued Date', key: 'date' },
  { title: 'Balance', key: 'balance' },
  { title: 'Actions', key: 'actions', sortable: false },
]


// Original invoice data
const invoiceData = ref({
  invoices: [
    {
      id: 5036,
      issuedDate: '2026-01-19',
      total: 3171,
      invoiceStatus: 'Paid',
      balance: 0,
      dueDate: '2026-01-25'
    },
    {
      id: 5035,
      issuedDate: '2026-01-20',
      total: 4263,
      invoiceStatus: 'Past Due', // unpaid
      balance: 4263,
      dueDate: '2026-01-12'
    },
    {
      id: 5034,
      issuedDate: '2026-01-10',
      total: 4836,
      invoiceStatus: 'Paid',
      balance: 0,
      dueDate: '2026-01-22'
    },
    {
      id: 5032,
      issuedDate: '2026-01-31',
      total: 5181,
      invoiceStatus: 'Past Due', // unpaid
      balance: 5181,
      dueDate: '2026-01-29'
    },
    {
      id: 5031,
      issuedDate: '2026-01-21',
      total: 3313,
      invoiceStatus: 'Past Due', // unpaid
      balance: 3313,
      dueDate: '2026-01-09'
    }
  ],
  totalInvoices: 5
})

const invoices = computed(() => {
  return invoiceData.value?.invoices
    .slice()
    .sort((a, b) => {
      const aUnpaid = a.invoiceStatus !== 'Paid' ? 0 : 1
      const bUnpaid = b.invoiceStatus !== 'Paid' ? 0 : 1
      if (aUnpaid !== bUnpaid) return aUnpaid - bUnpaid
      return new Date(a.dueDate) - new Date(b.dueDate)
    })
})


const getInvoiceRowClass = item => {
  return item.invoiceStatus !== 'Paid' ? 'invoice-unpaid' : ''
}

const totalInvoices = computed(() => invoiceData.value?.totalInvoices ?? 0)


// 👉 Invoice balance variant resolver
const resolveInvoiceBalanceVariant = (balance, total) => {
  if (balance === total)
    return {
      status: 'Unpaid',
      chip: { color: 'error' },
    }
  if (balance === 0)
    return {
      status: 'Paid',
      chip: { color: 'success' },
    }
  
  return {
    status: balance,
    chip: { variant: 'text' },
  }
}

const resolveInvoiceStatusVariantAndIcon = status => {
  if (status === 'Partial Payment')
    return {
      variant: 'warning',
      icon: 'tabler-chart-pie',
    }
  if (status === 'Paid')
    return {
      variant: 'success',
      icon: 'tabler-check',
    }
  if (status === 'Downloaded')
    return {
      variant: 'info',
      icon: 'tabler-arrow-down',
    }
  if (status === 'Draft')
    return {
      variant: 'primary',
      icon: 'tabler-folder',
    }
  if (status === 'Sent')
    return {
      variant: 'secondary',
      icon: 'tabler-mail',
    }
  if (status === 'Past Due')
    return {
      variant: 'error',
      icon: 'tabler-alert-circle',
    }
  
  return {
    variant: 'secondary',
    icon: 'tabler-x',
  }
}

</script>

<template>
  <VCard
    v-if="invoices"
    id="invoice-list"
    title="Billing History"
  >
    <VCardText class="d-flex align-center flex-wrap gap-4">
      <!-- 👉 Create invoice -->

      <div class="d-flex gap-2">
        <VLabel>Show</VLabel>
        <AppSelect
          v-model="itemsPerPage"
          :items="[5, 10, 20, 25, 50]"
        />
      </div>

    

      <VSpacer />

      <div class="d-flex align-end flex-wrap gap-4">
        <!-- 👉 Search  -->
        <div class="invoice-list-search">
          <AppTextField
            v-model="searchQuery"
            placeholder="Search Invoice"
          />
        </div>
        <div class="invoice-list-status">
          <AppSelect
            v-model="selectedStatus"
            placeholder="Invoice Status"
            clearable
            clear-icon="tabler-x"
            :items="['Downloaded', 'Draft', 'Sent', 'Paid', 'Partial Payment', 'Past Due']"
            style="inline-size: 12rem;"
          />
        </div>
      </div>
    </VCardText>

    <VDivider />

    <!-- SECTION DataTable -->
   <VDataTableServer
  v-model="selectedRows"
  v-model:items-per-page="itemsPerPage"
  v-model:page="page"
  show-select
  :items-length="totalInvoices"
  :headers="headers"
  :items="invoices"
  class="text-no-wrap"
  @update:options="updateOptions"
  item-class="getInvoiceRowClass"
>
  <!-- ID -->
  <template #item.id="{ item }">
    <RouterLink :to="item?.id ? '/billing/' + item.id : '#'">
      #{{ item.id }}
    </RouterLink>
  </template>

  <!-- Status -->
  <template #item.status="{ item }">
    <VChip
      :color="item.invoiceStatus === 'Paid' ? 'success' : 'error'"
      size="small"
      label
    >
      {{ item.invoiceStatus }}
    </VChip>
  </template>

  <!-- Total -->
  <template #item.total="{ item }">
    ${{ item.total }}
  </template>

  <!-- Issued Date -->
  <template #item.date="{ item }">
    {{ item.issuedDate }}
  </template>

  <!-- Balance -->
  <template #item.balance="{ item }">
    <div :class="item.invoiceStatus === 'Paid' ? 'text-success' : 'text-danger font-weight-bold'">
      {{ item.invoiceStatus === 'Paid' ? 'Paid' : `$${item.total}` }}
    </div>
  </template>

  <!-- Actions -->
  <template #item.actions="{ item }">
  <div class="d-flex gap-2">
    <!-- Show Pay Now only if invoice is unpaid -->
    <VBtn
      v-if="item.invoiceStatus !== 'Paid'"
      color="primary"
      variant="tonal"
     
    >
      Pay Now
    </VBtn>

    <!-- Optional: view invoice for all -->
    <VBtn
      color="secondary"
      variant="outlined"
      :to="`/billing/${item.id}`"
    >
      View
    </VBtn>
  </div>
</template>


  <!-- Pagination -->
  <template #bottom>
    <TablePagination
      v-model:page="page"
      :items-per-page="itemsPerPage"
      :total-items="totalInvoices"
    />
  </template>
</VDataTableServer>


    <!-- !SECTION -->
  </VCard>
</template>

<style lang="scss">
#invoice-list {
  .invoice-list-actions {
    inline-size: 8rem;
  }

  .invoice-list-search {
    inline-size: 12rem;
  }
}

.invoice-unpaid {
  background-color: rgba(255, 230, 230, 0.5); // subtle red
}

.text-danger {
  color: #d32f2f;
}

.text-success {
  color: #2e7d32;
}


</style>

