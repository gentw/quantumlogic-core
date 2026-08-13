<script setup>
const { t } = useI18n()


import * as feather from 'feather-icons';
import useNotificationsList from './useNotificationsList';

import AddNewNotification from './AddNewNotification.vue';

const options = ref({
  page: 1,
  itemsPerPage: 5,
  sortBy: [''],
  sortDesc: [false],
})
const isDialogVisible = ref(false);
const {
  fetchAdmins,
  tableColumns,
  perPage,
  currentPage,
  totalUsers,
  dataMeta,
  perPageOptions,
  searchQuery,
  sortBy,
  orderBy,
  isSortDirDesc,
  refSaleListTable,
  refetchData,
  usersDatas,
  refUserListTable,
} = useNotificationsList()

onMounted(()=>{
  fetchAdmins();

  console.log("XXXXXXXXXXXXXXXX",usersDatas.value)
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


// Data table options
// const itemsPerPage = ref(10)
const page = ref(1)

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const users = computed(() => usersDatas.value)

console.log("from gent", users);
// const totalClients = computed(() => clientsDatas.value.totalClients)

</script>
<template>
  <!-- <section v-if="clients"> -->
    <AddNewNotification v-model:showAlert="isDialogVisible"/>
    <section>
    <VCard id="clients-list"
      :title="$t('notifications.manage')"

    >
     
      <VBtn type="button" class="add-user-btn" @click="isDialogVisible = true">{{ $t('notifications.new') }}</VBtn>
     
      <VCardText class="pb-0">
        <div>{{ $t('notifications.subtitle') }}</div>
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
                          :placeholder="$t('common.search')"
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
        :items-length="totalUsers"
        :headers="tableColumns"
        :items="users"
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
              {{ paginationMeta(options, totalUsers) }}
            </p>
          

              <VPagination
                v-model="currentPage"
                :total-visible="$vuetify.display.smAndDown ? 3 : 3"
                :length="Math.ceil(totalUsers / perPage)"
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
