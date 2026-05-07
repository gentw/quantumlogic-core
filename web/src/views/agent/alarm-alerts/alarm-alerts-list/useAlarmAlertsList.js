// import { useToast } from 'vue-toastification/composition'
import { agentAlarmAlertsStore } from "../agentAlarmAlertsStore";
export default function useAlarmAlertsList() {
    // const toast = useToast()
    const _alarmAlertsStore = agentAlarmAlertsStore();


    const refAlarmListTable = ref(null)

    
    // Table Handlers
    const tableColumns = reactive([
        {
        title: 'PAISJA',
        key: 'alarm_description',
        },
        {
          title: 'KLIENTI',
          key: 'client.name',
          },
        {
        title: 'PREZENCA',
        key: 'is_me',
        },
        {
          title: 'STATUSI',
          key: 'status',
        },

        {
        title: 'DATA',
        key: 'date',
        },
        {
        title: 'KOHA',
        key: 'time',
        },
        {
        title: '',
        key: 'actions',
        }
    ])

   


    const perPage = ref(10)
    const totalAlarms = ref(0)
    const alarms = ref([]);
    const currentPage = ref(1)
    const perPageOptions = [10, 25, 50, 100]
    const searchQuery = ref('')
    const sortBy = ref('id')
    const isSortDirDesc = ref(true)
    const statusFilter = ref(null)
    const rangePicker = ref(null);

    // const alarmsDatas = computed(() => alarmData);
    const alarmsDatas = ref([]);
    const dataMeta = computed(() => {
    const localItemsCount = refAlarmListTable.value ? refAlarmListTable.value.localItems.length : 0
    return {
        from: perPage.value * (currentPage.value - 1) + (localItemsCount ? 1 : 0),
        to: perPage.value * (currentPage.value - 1) + localItemsCount,
        of: totalAlarms.value,
    }
    })

    const respondToAlarmStatus = ref(false)

    const refetchData = () => {
        fetchAlarms();
    }
    
    const respondToAlarm = async (alarmId, alarmData) => {
      await _alarmAlertsStore.respondToAlarm(alarmId, alarmData).then(
        response => {
          // console.log("gent", response);
          if (response && response.status == 'success') {
            respondToAlarmStatus.value = true; 
          }
        }
      )
    }

    const changeStatusByAgent = async (alarmId, responseData) => {
      await _alarmAlertsStore.changeStatusByAgent(alarmId, responseData).then(
        response => {
          if (response && response.status == 'success') {
            respondToAlarmStatus.value = true; 
          }
        }
      )
    }

    
    
    const fetchAlarms = async () => {
        console.log('Fetching alarms...', _alarmAlertsStore);
        await _alarmAlertsStore.fetchAlarms({
          query: {
            q: searchQuery.value,
            perPage: perPage.value,
            page: currentPage.value,
            sortBy: sortBy.value,
            sortDesc: isSortDirDesc.value,
            status: statusFilter.value,
            rangePicker: rangePicker.value,
          }
        }).then(response => {
        console.log('Full API Response:', response.data.value);
                // Assuming the data is directly available under response.data
          if (response && response.data) {
            const { alarms, total } = response.data.value; // Adjust based on actual structure
      
            // Set the reactive variables
            alarmsDatas.value = alarms || []; // Populate alarmsDatas
            totalAlarms.value = total || 0; // Populate totalAlarms
      
            console.log('Alarms Datas:', alarmsDatas.value); // Verify the value
          } else {
            console.error('No data found in response');
            alarmsDatas.value = []; // Reset alarmsDatas if no data
          }
        });
      };




     
      watch([currentPage, perPage, statusFilter, rangePicker, searchQuery], () => {
    
        console.log('search1', searchQuery.value);
        refetchData()         

      });
      
    
    return {
        fetchAlarms,
        tableColumns,
        perPage,
        currentPage,
        totalAlarms,
        dataMeta,
        perPageOptions,
        searchQuery,
        sortBy,
        isSortDirDesc,
        refAlarmListTable,
        alarms,
        refetchData,
    
        // Extra Filters
        alarmsDatas,
        statusFilter,
        rangePicker,
        respondToAlarm,
        respondToAlarmStatus,
        changeStatusByAgent
      }
}
