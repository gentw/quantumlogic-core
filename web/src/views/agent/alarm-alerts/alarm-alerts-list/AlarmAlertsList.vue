<script>
import AlarmAlertsListFilters from './AlarmAlertsListFilters.vue';

import * as feather from 'feather-icons';
import { onBeforeMount } from 'vue';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
import useAlarmAlertsList from './useAlarmAlertsList';
const $toast = useToast();
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
          title: 'E zgjidhur',
          value: 'resolved'
        },
        {
          title: 'Patrula nisur',
          value: 'patrol_dispatched',
        },
        {
          title: 'Ne pritje',
          value: 'pending',
        },
        {
          title: 'Pa pergjigje',
          value: 'no_response',
        }
      ])
      // pending
            // no_response
            // security_dispatched
            // resolved

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
            text: '',
            icon: 'help-circle'
          }        
      }

      const newAlarmsCount = ref(0);

      const renderFeatherIcon = (iconName, size) => {
        const icon = feather.icons[iconName];
        
        if (icon) {
          return icon.toSvg({ width: size, height: size });
        }

        // Return default or fallback icon if necessary
        return feather.icons['alert-circle'].toSvg(); // Default fallback icon
      };

      onBeforeMount(() => {
        subscribe()
      });

      watch(() => newAlarmsCount.value, (newValue) => {
          if(newValue) {
            const rows = document.querySelectorAll('tr.v-data-table__tr');
          
          for (let i = 0; i < newAlarmsCount.value; i++) {
            rows[i].classList.add('new-alarm');
          }
        // Loop through the first `number` rows and add the 'new-alarm' class
            
          }
      }, { immediate: true });

      const subscribe = () => {
        let pusher = new Pusher('58603e7879be559844d7', { cluster: 'eu' })
        // const userId = ref(useCookie('userData').value.id);
        pusher.subscribe(`agents_base_notified_for_no_response`)

        pusher.bind('NoResponseAlarmClient', data => {
          // alert(1);
          fetchAlarms().then(()=>{
            newAlarmsCount.value++;
          });
          
          
        })
      }

      const changeStatus = async (alarmId, responseData) => {
        await changeStatusByAgent(alarmId, responseData).then(() => {
          $toast.success('Statusi i alarmit u ndryshua me sukses!');

          fetchAlarms().then(()=>{
            if(newAlarmsCount.value != 0) {
              newAlarmsCount.value--;
            }
          });          
        })
      }    

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
        refAlarmListTable,
        changeStatusByAgent
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

      return { tableColumns, alarms, options, resolveIsMeVariant, _statusOptions, fetchAlarms, totalAlarms, perPage, currentPage, alarmsData, refAlarmListTable, renderFeatherIcon, searchQuery, statusFilter, rangePicker, _resolveStatus, changeStatus }
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

      <!-- Actions -->
      <template #item.actions="{ item }">
        <IconBtn>
          <VIcon icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList>
              <VListItem
                value="delete"
                @click="changeStatus(item.id, 
                {
                  'response': 'patrol_dispatched'
                }
                )"
              >
                Nis patrullen
              </VListItem>
              <VListItem
                value="delete"
                @click="changeStatus(item.id, 
                {
                  'response': 'resolved'
                }
                )"
              >
                E zgjidhur
              </VListItem>

              <VListItem
                value="delete"
                @click="changeStatus(item.id, 
                {
                  'response': 'pending'
                }
                )"
              >
                Ne Pritje
              </VListItem>
            </VList>
          </VMenu>
        </IconBtn>
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
<style type="scss" scoped>
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

/* Blinking background for new alarm rows */
@keyframes blink {
  0% {
    background-color: red;
  }

  50% {
    background-color: darkred;
  }

  100% {
    background-color: red;
  }
}

/* Apply blinking animation to new alarm rows */
.new-alarm {
  animation: blink 1s infinite;
  color: white; /* Ensure text is readable on the red background */
}

</style>

