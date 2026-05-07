<script setup>


import useClientList from '@/views/admin/users/client/useClientList';
import * as feather from 'feather-icons';

const options = ref({
  page: 1,
  itemsPerPage: 5,
  sortBy: [''],
  sortDesc: [false],
})

const filterUsers = ref(0);

const {
  fetchClients,
  tableColumns,
  perPage,
  currentPage,
  alarms,
  totalClients,
  dataMeta,
  perPageOptions,
  searchQuery,
  sortBy,
  orderBy,
  isSortDirDesc,
  refSaleListTable,
  refetchData,
  clientsDatas,
  refClientListTable,
  statusFilter,
} = useClientList()

onMounted(()=>{
  fetchClients();

  console.log("XXXXXXXXXXXXXXXX",clientsDatas.value)
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
    },
    {
      title: 'Fshije përgjithmonë',
      value: 'delete_user',
      // to: {
      //   name: 'apps-invoice-edit-id',
      //   params: { id: paramId },
      // },
    },
    {
      title: 'Reseto Fjalëkalimin',
      value: 'reset_password',
    },
    {
      title: 'Çaktivizo',
      value: 'disable_user',
    },
  ]
})

const _statusOptions = ref([
  {
    title: 'Të gjithë',
    value: '1'
  },
  {
    title: 'Klientë në pritje',
    value: '2',
  },
  {
    title: 'Kërkesë për editim',
    value: '3',
  }
]);

// Data table options
// const itemsPerPage = ref(10)
const page = ref(1)

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const clients = computed(() => clientsDatas.value)

console.log("from gent", clients);
// const totalClients = computed(() => clientsDatas.value.totalClients)

</script>
<template>
  <!-- <section v-if="clients"> -->
    <section>
    <VCard id="clients-list"

    >
     
      
      <!-- SECTION Datatable -->
      <VDataTableServer
        v-model="selectedRows"
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        show-select
        :items-length="totalClients"
        :headers="tableColumns"
        :items="clients"
        item-value="id"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- id -->
        <!-- <template #item.id="{ item }">
          <RouterLink :to="{ name: 'apps-invoice-preview-id', params: { id: item.id } }">
            #{{ item.id }}
          </RouterLink>
        </template> -->

        

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-trash" />
          </IconBtn>          

          <!-- <IconBtn :to="{ name: 'apps-invoice-preview-id', params: { id: item.id } }"> -->
          <IconBtn>
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <MoreBtn
            :menu-list="computedMoreList(item.id)"
            item-props
            color="undefined"
          />
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
              {{ paginationMeta(options, totalClients) }}
            </p>
          

              <VPagination
                v-model="currentPage"
                :total-visible="$vuetify.display.smAndDown ? 3 : 3"
                :length="Math.ceil(totalClients / perPage)"
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
