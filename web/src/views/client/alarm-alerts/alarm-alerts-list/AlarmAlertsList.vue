<script>
import AlarmAlertsListFilters from './AlarmAlertsListFilters.vue';

import * as feather from 'feather-icons';
import useAlarmAlertsList from './useAlarmAlertsList';

export default {
  components: {
    AlarmAlertsListFilters
  },

  setup() {
      const options = ref({
        page: 1,
        itemsPerPage: 5,
        sortBy: [''],
        sortDesc: [false],
      })


      const _statusOptions = ref([
        {
          title: 'E pa evidentuar',
          value: '0'
        },
        {
          title: 'Jam unë',
          value: '1',
        },
        {
          title: 'Nuk jam unë',
          value: '2',
        }
      ])

      const _resolveStatus = status => {
        if (status === 'resolved')
          return { text: 'E zgjidhur', color: 'success' }
        if (status === 'no_response')
          return { text: 'Pa përgjigje', color: 'primary' }
        if (status === 'patrol_dispatched')
          return { text: 'Patrula nisur', color: 'info' }
        if (status === 'pending')
          return { text: 'Në pritje', color: 'warning' }
      }

      const resolveIsMeVariant = (is_me) => {
        if (is_me === 1)
          return {
            color: 'success',
            text: 'Jam une',
            icon: 'unlock'
          }
        else if (is_me === 2)
          return {
            color: 'primary',
            text: 'Nuk jam une',
            icon: 'lock'
          }
        else
        return {
            color: 'warning',
            text: 'E pa-evidentuar',
            icon: 'help-circle'
          }        
      }

      const renderFeatherIcon = (iconName, size) => {
        const icon = feather.icons[iconName];
        
        if (icon) {
          return icon.toSvg({ width: size, height: size });
        }

        // Return default or fallback icon if necessary
        return feather.icons['alert-circle'].toSvg(); // Default fallback icon
      };

      // onMounted(() => {
      //   fetchAlarms();
      // })
      const data = ref('');

      const alarmsData = computed(() => alarmsDatas.value);

      // watch(alarmsData, (newVal) => {
      //   console.log('alarmsDatas changed:', newVal); // Log changes to alarmsDatas
      // });
      // const alarmsData = ref([]);
      const {
        fetchAlarms,
        tableColumns,
        perPage,
        currentPage,
        alarms,
        totalAlarms,
        dataMeta,
        perPageOptions,
        searchQuery,
        sortBy,
        isSortDirDesc,
        refSaleListTable,
        refetchData,
        resolveStatus,
        rangePicker,
        alarmsDatas,
        statusFilter,
        refAlarmListTable
      } = useAlarmAlertsList()

      onMounted(()=>{
        // fetchAlarms();
         fetchAlarms();
        console.log("XXXXXXXXXXXXXXXX",alarmsData.value)

        setTimeout(()=>{
          console.log(statusFilter.value);
        },10000)
        // fetchAlarms();
        // console.log("1", alarms.value)
        // setTimeout(()=> {
        //   // console.log(totalAlarms.value)
        //   console.log("x",alarmsData.value)
        // },1000)
        
        // console.log(fetchAlarms());
      });

      return { tableColumns, alarms, options, resolveIsMeVariant, _statusOptions, fetchAlarms, totalAlarms, perPage, currentPage, alarmsData, refAlarmListTable, renderFeatherIcon, searchQuery, statusFilter, rangePicker, _resolveStatus }
  }
}

</script>

<template>
  <alarm-alerts-list-filters
    v-model:searchQuery.sync="searchQuery"
    v-model:statusFilter.sync="statusFilter"
    v-model:dateRange.sync="rangePicker"
    :_status-options="_statusOptions"
    :date-range="null"

    />
  <VDataTable
    :headers="tableColumns"
    :items-per-page="10"
    :items="alarmsData"
    ref="refAlarmListTable"
  >
    <template #item.is_me="{ item }">
      <div class="d-flex align-center custom-is_me">
        <VChip
          :color="resolveIsMeVariant(item.is_me).color"
          class="font-weight-medium"
        
        >
          <i class="alert-icon" v-html="renderFeatherIcon(resolveIsMeVariant(item.is_me).icon, 20)" />
        </VChip>
        <div class="ml-2 text-default">
           {{ resolveIsMeVariant(item.is_me).text }}
        </div>
      </div>
    </template>

    <template #item.status="{ item }">
        <VChip
          label
          :color="_resolveStatus(item.status)?.color"
          size="small"
        >
          {{ _resolveStatus(item.status)?.text }}
        </VChip>
      </template>

    <template #item.action="{ item }">
      <RouterLink
      
      :to="{ 
        name: 'client-alarm-alerts-details-id',       
        params: { id: item.id }, 
        query: { name: item.alarm_description } 
        }">
        <div class="d-flex align-center action">
          <i class="alert-icon" v-html="renderFeatherIcon('arrow-right', 20)" />
          <!-- <div class="ml-2 text-default">
            {{ resolveIsMeVariant(item.is_me).text }}
          </div> -->
        </div>
      </RouterLink>
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
          {{ paginationMeta(options, totalAlarms) }}
        </p>
       

          <VPagination
            v-model="currentPage"
            :total-visible="$vuetify.display.smAndDown ? 3 : 3"
            :length="Math.ceil(totalAlarms / perPage)"
            :first-icon="null" :last-icon="null"
           
          />
        </div>
      </VCardText>
    </template>
  </VDataTable>
</template>
<style type="scss">
.v-pagination__first {
  display: none !important;
}

.v-pagination__last {
  display: none !important;
}

.v-pagination .v-pagination__list .v-pagination__item--is-active .v-btn:not([class*="text-"]) {
  border-radius: 0;
  background: #f02227;
  color: #fff;
  font-weight: bold;
}

.v-pagination__item .v-btn {
  color: #374151;
}

.v-btn--variant-tonal .v-btn__underlay {
  background: #fff;
}

.v-pagination__item,
.v-pagination__first,
.v-pagination__prev,
.v-pagination__next,
.v-pagination__last {
  margin: 0;
}

.v-pagination__list {
  border: 1px solid #eee;
}

.text-disabled {
  color: #374151 !important;
}

.v-table--density-default {
  --v-table-row-height: 65px !important;
}

.v-chip__content {
  /* z-index: 999999; */
  z-index: 1003;
}

.v-chip__content .feather {
  color: #fff;
}

.v-chip__content i {
  display: flex;
  align-content: center;
}

.v-chip--variant-tonal .v-chip__underlay {
  opacity: 1;
}

.v-data-table__td {
  font-weight: bold;
}

.flatpickr-calendar {
  z-index: 999999 !important;
}

.action .feather {
  color: #374151;
}

@media (max-width: 768px) {
  .custom-is_me {
    flex-direction: column;
    padding-block: 10px;
    padding-inline: 0;
  }
}

.v-chip__content {
  color: #fff;
}
</style>

