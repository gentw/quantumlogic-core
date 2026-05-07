// import { useToast } from 'vue-toastification/composition'
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
import { userStore } from "../userStore";

const $toast = useToast();
export default function useClientList() {
    // const toast = useToast()
    const _userStore = userStore();


    const refClientListTable = ref(null)

    
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
        title: 'Deaktiv',
        key: 'deactivated',
      },
      {
        title: 'Bllok',
        key: 'blocked',
      },
      {
        title: 'Editim',
        key: 'update_request',
      },
     
      {
        title: 'Opsionet',
        key: 'actions',
        sortable: false,
      },
    ])

    const perPage = ref(10)
    const totalClients = ref(0)
    const clients = ref([]);
    const currentPage = ref(1)
    const perPageOptions = [10, 25, 50, 100]
    const searchQuery = ref('')
    const sortBy = ref('id')
    const orderBy = ref('id')
    const isSortDirDesc = ref(true)
    const statusFilter = ref(null)
    const agentRegistered = ref(false)

    // const alarmsDatas = computed(() => alarmData);
    const clientsDatas = ref([]);
    const dataMeta = computed(() => {
    const localItemsCount = refClientListTable.value ? refClientListTable.value.localItems.length : 0
    return {
        from: perPage.value * (currentPage.value - 1) + (localItemsCount ? 1 : 0),
        to: perPage.value * (currentPage.value - 1) + localItemsCount,
        of: totalAlarms.value,
    }
    })

    const refetchData = () => {
        fetchClients();
    }
    
    
    const registerClientFromAdmin = async (clientData) => {       
      await _userStore.registerClientFromAdmin(clientData).then(response => {
        console.log("GEX,", response.success)
        // alert(response.success)
        if (response.success == true) {            
          agentRegistered.value = true
        } else {
          agentRegistered.value = false
          $toast.error('Ka ndodhur nje gabim!');
        }
      }).catch(error => {
        if(error.data) {
          $toast.error(error.data.message || "Ju lutem plotesoni te gjitha fushat e kerkuara");
        }          
      });
    };

    const fetchClients = async () => {
        console.log('Fetching clients...', _userStore);
        await _userStore.fetchClients({
          query: {
            q: searchQuery.value,
            perPage: perPage.value,
            page: currentPage.value,
            sortBy: sortBy.value,
            orderBy: orderBy.value,
            sortDesc: isSortDirDesc.value,
            status: statusFilter.value,
          }
        }).then(response => {
        console.log('Full API Response:', response.data.value);
                // Assuming the data is directly available under response.data
          if (response && response.data) {
            const { users, total } = response.data.value; // Adjust based on actual structure
      
            // Set the reactive variables
            clientsDatas.value = users || []; // Populate alarmsDatas
            totalClients.value = total || 0; // Populate totalAlarms
      
            console.log('Clients Datas:', clientsDatas.value); // Verify the value
          } else {
            console.error('No data found in response');
            clientsDatas.value = []; // Reset alarmsDatas if no data
          }
        });
      };

      const blockUnblockUser = async (clientData) => {       
        await _userStore.blockUnblockUser(clientData).then(response => {
          // alert(response.success)
          if (response.status == "success") {            
            if(response.user.blocked == 1) {
              $toast.success('Ky perdorues u blloku me sukses!'); 
              refetchData();
            } else {
              $toast.success("Ky perdorues u zhblloku me sukses!"); 
              $toast.info("Ky perdorues poashtu u riaktivizua!"); 
              refetchData();
            }
          } else {
            $toast.error('Ka ndodhur nje gabim!');
          }
        }).catch(error => {
          if(error.data) {
            $toast.error("Ka ndodhur nje gabim :(");
          }          
        });
      };

      const deactivateUser = async (clientData) => {       
        await _userStore.deactivateUser(clientData).then(response => {
          // alert(response.success)
          if (response.status == "success") {            
            $toast.success('Ky perdorues u deaktivizua me sukses!');
            refetchData();
          } else {
            $toast.error('Ka ndodhur nje gabim!');
          }
        }).catch(error => {
          if(error.data) {
            $toast.error("Ka ndodhur nje gabim :(");
          }          
        });
      };
      
      const deleteClientFromAdmin = async (clientId) => {
       
        await _userStore.deleteClientFromAdmin(clientId).then(response => {
         
          // alert(response.success)
          if (response.status == 'success') {            
            $toast.success('Ky klient u fshi me sukses!');
            refetchData();
          } else {
            $toast.error('Ka ndodhur nje gabim!');
          }
        }).catch(error => {
          if(error.data) {
            $toast.error("Ka ndodhur nje gabim!");
          }          
        });
      };

     
     
      watch([currentPage, perPage, searchQuery, statusFilter], () => {    
        console.log('search1', searchQuery.value);
        refetchData()         
      });
      
    
    return {
        fetchClients,
        tableColumns,
        perPage,
        currentPage,
        totalClients,
        dataMeta,
        perPageOptions,
        searchQuery,
        sortBy,
        orderBy,
        isSortDirDesc,
        
        clients,
        refetchData,
        statusFilter,
        // Extra Filters
        clientsDatas,
        registerClientFromAdmin,
        agentRegistered,
        deleteClientFromAdmin,
        blockUnblockUser,
        deactivateUser
      }
}
