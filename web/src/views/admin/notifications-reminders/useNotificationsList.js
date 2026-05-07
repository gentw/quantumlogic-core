// import { useToast } from 'vue-toastification/composition'
import { userStore } from "@/views/admin/users/userStore";
export default function useClientList() {
    // const toast = useToast()
    const _userStore = userStore();


    const refUserListTable = ref(null)

    
    // Table Handlers per alarm alert list
    const tableColumns = reactive([
      {
        title: '#',
        key: 'id',
      },
      {
        title: 'Emri',
        key: 'name',
        sortable: false,
      },
      {
        title: 'Mbiemri',
        key: 'surname',
        sortable: false,
      },
      {
        title: 'Nr. Tel',
        key: 'phone',
      },
      {
        title: 'Adresa',
        key: 'address',
      },
      {
        title: 'Data Regjistrimit',
        key: 'created_at',
      },
     
      {
        title: 'Opsionet',
        key: 'actions',
        sortable: false,
      },
    ])

    const perPage = ref(10)
    const totalUsers = ref(0)
    const users = ref([]);
    const currentPage = ref(1)
    const perPageOptions = [10, 25, 50, 100]
    const searchQuery = ref('')
    const sortBy = ref('id')
    const orderBy = ref('id')
    const isSortDirDesc = ref(true)
    const statusFilter = ref(null)

    // const alarmsDatas = computed(() => alarmData);
    const usersDatas = ref([]);
    const dataMeta = computed(() => {
    const localItemsCount = refClientListTable.value ? refClientListTable.value.localItems.length : 0
    return {
        from: perPage.value * (currentPage.value - 1) + (localItemsCount ? 1 : 0),
        to: perPage.value * (currentPage.value - 1) + localItemsCount,
        of: totalAlarms.value,
    }
    })

    const refetchData = () => {
        fetchAdmins();
    }
    

    const fetchAdmins = async () => {
        console.log('Fetching clients...', _userStore);
        await _userStore.fetchClients({
          query: {
            q: searchQuery.value,
            perPage: perPage.value,
            page: currentPage.value,
            sortBy: sortBy.value,
            orderBy: orderBy.value,
            sortDesc: isSortDirDesc.value,
          }
        }).then(response => {
        console.log('Full API Response:', response.data.value);
                // Assuming the data is directly available under response.data
          if (response && response.data) {
            const { users, total } = response.data.value; // Adjust based on actual structure
      
            // Set the reactive variables
            usersDatas.value = users || []; // Populate alarmsDatas
            totalUsers.value = total || 0; // Populate totalAlarms
      
            console.log('Clients Datas:', usersDatas.value); // Verify the value
          } else {
            console.error('No data found in response');
            usersDatas.value = []; // Reset alarmsDatas if no data
          }
        });
      };
     
     
      watch([currentPage, perPage, searchQuery], () => {    
        console.log('search1', searchQuery.value);
        refetchData()         
      });
      
    
    return {
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
        
        users,
        refetchData,
        // Extra Filters
        usersDatas,
      }
}
