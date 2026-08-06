// import { useToast } from 'vue-toastification/composition'
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
import { domainStore } from "./domainStore";

const $toast = useToast();
export default function usedomainList() {
    // const toast = useToast()
    const _domainStore = domainStore();


    const refdomainListTable = ref(null)

    
    // Table Handlers per alarm alert list
    const tableColumns = reactive([
      {
        title: '#',
        key: 'id',
      },
      {
        title: 'Domain',
        key: 'domain',
        sortable: false,
      },
      {
        title: 'Status',
        key: 'status',
        sortable: false,
      },
      
     
      {
        title: 'Action',
        key: 'actions',
        sortable: false,
      },
    ])

    const perPage = ref(10)
    const totalDomains = ref(0)
    const domains = ref([]);
    const currentPage = ref(1)
    const perPageOptions = [10, 25, 50, 100]
    const searchQuery = ref('')
    const sortBy = ref('id')
    const orderBy = ref('id')
    const isSortDirDesc = ref(true)
    const statusFilter = ref(null)
    const agentRegistered = ref(false)

    // const alarmsDatas = computed(() => alarmData);
    const domainsDatas = ref([]);
    const dataMeta = computed(() => {
    const localItemsCount = refdomainListTable.value ? refdomainListTable.value.localItems.length : 0
    return {
        from: perPage.value * (currentPage.value - 1) + (localItemsCount ? 1 : 0),
        to: perPage.value * (currentPage.value - 1) + localItemsCount,
        of: totalAlarms.value,
    }
    })

    const refetchData = () => {
        fetchDomains();
    }
    
    
    const addDomain = async (domainData) => {       
      await _domainStore.addDomain(domainData).then(response => {
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

    const fetchDomains = async () => {
        console.log('Fetching domains...', _domainStore);
        await _domainStore.fetchDomains({
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
            domainsDatas.value = users || []; // Populate alarmsDatas
            totalDomains.value = total || 0; // Populate totalAlarms
      
            console.log('domains Datas:', domainsDatas.value); // Verify the value
          } else {
            console.error('No data found in response');
            domainsDatas.value = []; // Reset alarmsDatas if no data
          }
        });
      };

      // const blockUnblockUser = async (domainData) => {       
      //   await _userStore.blockUnblockUser(domainData).then(response => {
      //     // alert(response.success)
      //     if (response.status == "success") {            
      //       if(response.user.blocked == 1) {
      //         $toast.success('Ky perdorues u blloku me sukses!'); 
      //         refetchData();
      //       } else {
      //         $toast.success("Ky perdorues u zhblloku me sukses!"); 
      //         $toast.info("Ky perdorues poashtu u riaktivizua!"); 
      //         refetchData();
      //       }
      //     } else {
      //       $toast.error('Ka ndodhur nje gabim!');
      //     }
      //   }).catch(error => {
      //     if(error.data) {
      //       $toast.error("Ka ndodhur nje gabim :(");
      //     }          
      //   });
      // };

      // const deactivateUser = async (domainData) => {       
      //   await _userStore.deactivateUser(domainData).then(response => {
      //     // alert(response.success)
      //     if (response.status == "success") {            
      //       $toast.success('Ky perdorues u deaktivizua me sukses!');
      //       refetchData();
      //     } else {
      //       $toast.error('Ka ndodhur nje gabim!');
      //     }
      //   }).catch(error => {
      //     if(error.data) {
      //       $toast.error("Ka ndodhur nje gabim :(");
      //     }          
      //   });
      // };
      
      // const deletedomainFromAdmin = async (domainId) => {
       
      //   await _userStore.deletedomainFromAdmin(domainId).then(response => {
         
      //     // alert(response.success)
      //     if (response.status == 'success') {            
      //       $toast.success('Ky klient u fshi me sukses!');
      //       refetchData();
      //     } else {
      //       $toast.error('Ka ndodhur nje gabim!');
      //     }
      //   }).catch(error => {
      //     if(error.data) {
      //       $toast.error("Ka ndodhur nje gabim!");
      //     }          
      //   });
      // };

     
     
      watch([currentPage, perPage, searchQuery, statusFilter], () => {    
        console.log('search1', searchQuery.value);
        refetchData()         
      });
      
    
    return {
        fetchDomains,
        tableColumns,
        perPage,
        currentPage,
        totalDomains,
        dataMeta,
        perPageOptions,
        searchQuery,
        sortBy,
        orderBy,
        isSortDirDesc,
        
        domains,
        refetchData,
        statusFilter,
        // Extra Filters
        domainsDatas,
        addDomain,
        agentRegistered,
        // deleteDomain,
        // blockUnblockUser,
        // deactivateUser
      }
}
