<script setup>
import * as feather from 'feather-icons';
import useInvoiceList from './useInvoiceList';

const options = ref({
  page: 1,
  itemsPerPage: 5,
  sortBy: [''],
  sortDesc: [false],
})

const filterInvoices = ref(0);
const router = useRouter();

const {
  fetchAgents,
  tableColumns,
  perPage,
  currentPage,
  totalInvoices,
  dataMeta,
  perPageOptions,
  searchQuery,
  sortBy,
  orderBy,
  isSortDirDesc,
  refSaleListTable,
  refetchData,
  invoicesDatas,
  refInvoiceListTable,
  deleteAgentFromAdmin,
  blockUnblockUser,
  deactivateUser
} = useInvoiceList()

onMounted(()=>{
  fetchAgents();
});

const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};


const selectedRows = ref([])

const computedMoreList = computed(() => {
  return paramId => [
    {
      title: 'Blloko/Zhblloko',
      value: 'block_unblock',
      paramId
    },
    {
      title: 'Fshije përgjithmonë',
      value: 'delete_user',
      paramId
    },
    {
      title: 'Reseto Fjalëkalimin',
      value: 'reset_password',
      paramId
    },
    {
      title: 'Çaktivizo',
      value: 'disable_user',
      paramId
    },
  ]
})


const resolveBlocked = status => {
  if (status === 1)
    return { text: 'BLLOKUAR', color: 'primary' }
  if (status === 0)
    return { text: 'AKTIV', color: 'success' }

}

const resolveDeactivated = status => {
  if (status === 1)
    return { text: 'DEAKTIV', color: 'primary' }
  if (status === 0)
    return { text: 'AKTIV', color: 'success' }

}

// Data table options
// const itemsPerPage = ref(10)
const page = ref(1)

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

//const invoices = computed(() => invoicesDatas.value)

const invoices = [
  {
    id: 5036,
    client: 'Andrew Burns',
    clientEmail: 'andrew@company.com',
    total: 3171,
    currency: 'EUR',
    status: 'Paid',
    paymentMethod: 'Bank Transfer',
    issuedDate: '2026-01-19',
    dueDate: '2026-01-25',
    paidAt: '2026-01-20',
  },
  {
    id: 5035,
    client: 'Dana Carey',
    clientEmail: 'dana@startup.io',
    total: 4263,
    currency: 'EUR',
    status: 'Unpaid',
    paymentMethod: 'Wise',
    issuedDate: '2026-01-20',
    dueDate: '2026-01-23',
    paidAt: null,
  },
]


console.log("from gent", invoices);

const handleMenuClick = (item) => {
  console.log("option ", item);
  if(item.value == 'block_unblock') {
    blockUnblockUser(
      {
        user_id: item.paramId
      }
    )
  }

  if(item.value == 'disable_user') {
    deactivateUser(
      {
        user_id: item.paramId
      }
    )
  }

  if(item.value == 'reset_password') {
    router.push({
        name: 'admin-agents-id',
        params: { id: item.paramId }
    });
  }

  if(item.value == 'delete_user') {
    deleteAgentFromAdmin(item.paramId)
  }
  // alert(id)
  // Add your custom logic here, e.g., routing or other actions
};
// const totalClients = computed(() => clientsDatas.value.totalClients)

</script>
<template>
  <!-- <section v-if="clients"> -->
    <section>
    <VCard id="clients-list"
      title="Faturat"

    >
      <RouterLink :to="{ name: 'admin-agents-add-agent'}">
        <VBtn type="button" class="add-user-btn">Shto Fature te re</VBtn>
      </RouterLink>
      <VCardText class="pb-0">
        <div>Lista e të gjitha faturave</div>
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <!-- <span>Show</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: -1, title: 'All' },
              ]"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = parseInt($event, 10)"
            /> -->
          </div>
          <!-- 👉 Create invoice -->
          <!-- <VBtn
            prepend-icon="tabler-plus"
            :to="{ name: 'apps-invoice-add' }"
          >
            Create invoice
          </VBtn> -->
        </div>

        <div>
          <div class="clients-list-filter">
            <VCard
                  class="clients__filters">
              <VCardText class="clients__filters--text">
                  <VRow>
                    <VCol
                      cols="4"
                      md="4"
                    >
                      <div class="position-relative filterSection">
                        <AppTextField
                          v-model="searchQuery"
                          placeholder="Kerko ..."
                          append-inner-icon="tabler-search"
                          single-line
                          hide-details
                          dense
                          outlined
                          :style="{ '--placeholder-color': 'red' }"
                        />
                        <!-- <AppSelect
                          :items="filterOptions"
                          placeholder="Filter"
                          v-model="selectedFilter"
                          clearable
                          clear-icon="tabler-x"
                        /> -->
                      </div>
                    </VCol>
                    
                    
                    <VCol
                      offset-md="3"
                    >
                      <!-- <AppTextField
                        v-model="searchQuery"
                        placeholder="Search ..."
                        append-inner-icon="tabler-search"
                        single-line
                        hide-details
                        dense
                        outlined
                      /> -->
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
          </div>

          <!-- 👉 Select status -->
         
        </div>
      </VCardText>
      <VDivider />

      <!-- SECTION Datatable -->
      <VDataTableServer
        v-model="selectedRows"
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        show-select
        :items-length="totalInvoices"
        :headers="tableColumns"
        :items="invoices"
        item-value="id"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Invoice ID -->
        <template #item.id="{ item }">
          <RouterLink
            :to="`/admin/billing/${item.id}`"
            class="font-weight-medium text-primary"
          >
            #{{ item.id }}
          </RouterLink>
        </template>

        <!-- Client -->
        <template #item.client="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{ item.client }}</span>
            <span class="text-body-2 text-disabled">{{ item.clientEmail }}</span>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="item.status === 'Paid' ? 'success' : item.status === 'Overdue' ? 'error' : 'warning'"
            size="small"
            label
          >
            {{ item.status }}
          </VChip>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span class="font-weight-medium">
            €{{ item.total }}
          </span>
        </template>

        <!-- Payment Method -->
        <template #item.paymentMethod="{ item }">
          <VChip variant="tonal" size="small">
            {{ item.paymentMethod }}
          </VChip>
        </template>

        <!-- Issued -->
        <template #item.issuedDate="{ item }">
          {{ item.issuedDate }}
        </template>

        <!-- Due -->
        <template #item.dueDate="{ item }">
          <span
            :class="item.status !== 'Paid' ? 'text-error font-weight-medium' : ''"
          >
            {{ item.dueDate }}
          </span>
        </template>

        <!-- Paid At -->
        <template #item.paidAt="{ item }">
          <span v-if="item.paidAt">
            {{ item.paidAt }}
          </span>
          <span v-else class="text-disabled">
            —
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-2">
            <!-- Pay Now / Review Proof -->
            <VBtn
              v-if="item.status !== 'Paid'"
              color="primary"
              size="small"
              variant="tonal"
            >
              Review / Mark Paid
            </VBtn>

            <VBtn
              size="small"
              variant="outlined"
              :to="`/admin/billing/${item.id}`"
            >
              View
            </VBtn>
          </div>
        </template>

        <template #bottom>
          <VCardText class="pt-2">
            <div class="d-flex flex-wrap justify-center justify-sm-space-between gap-y-2 mt-2">
              <!-- <VTextField
                v-model="options.itemsPerPage"
                label="Rows per page:"
                type="number"
                min="-1"
                max="15"
                hide-details
                variant="underlined"
                style="max-inline-size: 8rem;min-inline-size: 5rem;"
              /> -->
              <p class="text-disabled mb-0">
              {{ paginationMeta(options, totalInvoices) }}
            </p>
          

              <VPagination
                v-model="currentPage"
                :total-visible="$vuetify.display.smAndDown ? 3 : 3"
                :length="Math.ceil(totalInvoices / perPage)"
                :first-icon="null" :last-icon="null"
              
              />
            </div>
          </VCardText>
        </template>
      </VDataTableServer>
    <!-- !SECTION -->
    </VCard>
  </section>
  <!-- <section v-else>
    <VCard>
      <VCardTitle>Nuk u gjet asnje klient!</VCardTitle>
    </VCard>
  </section> -->
</template>
<style type="scss">
#clients-list .v-btn--icon {
  border: 1px solid rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  border-radius: 5px;
  background: #f4f4f4;
  margin-block: 10px;
  margin-inline: 5px;
}

#clients-list .clients__filters--text {
  padding-inline: 0 !important;
}

#clients-list .v-field {
  border-radius: 5px;
}

#clients-list .v-field__field {
  padding-block: 4px !important;
  padding-inline: 0;
}

#clients-list .v-input .v-field .v-field__input::placeholder {
  color: #374151 !important;
  font-size: 0.95rem;
}

#clients-list .v-input.v-input--density-comfortable .v-field .v-field__clearable > .v-icon {
  inline-size: 1.5rem;
}

#clients-lis .v-input.v-input--density-comfortable .v-field .v-field__append-inner > .v-icon {
  font-size: 1.4rem;
}

#clients-list .v-card-item {
  padding-block-end: 0 !important;
}

#clients-list .v-btn.v-btn--density-default {
  border-color: #d0d0d0 !important;
  border-radius: 3px;
  block-size: calc(var(--v-btn-height) + 8px);
  color: #000;
}

#clients-list .feather {
  display: inline-block;
  color: #000;
  margin-block-start: 8px;
  transform: rotate(-90deg);
}

#clients-list .v-data-table__td {
  color: #374151;
}

.add-user-btn {
  position: absolute;
  background-color: #d81b1b !important;
  color: #fff !important;
  inset-inline-end: 50px;
}
</style>
