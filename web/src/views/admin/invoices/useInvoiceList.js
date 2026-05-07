// import { useToast } from 'vue-toastification/composition'
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
import { invoiceStore } from "./invoiceStore";
const router = useRouter()
const $toast = useToast();

export default function useInvoiceList() {
    // const toast = useToast()
    const _invoiceStore = invoiceStore();


    const refInfoiceListTable = ref(null)
    
    // Table Handlers per alarm alert list

    const tableColumns = reactive([
      { title: '#Invoice', key: 'id' },
    
      // Client info (ADMIN ONLY)
      { title: 'Client', key: 'client' },
      { title: 'Email', key: 'clientEmail' },
    
      // Financial
      { title: 'Total', key: 'total' },
      { title: 'Currency', key: 'currency' },
    
      // Payment & status
      { title: 'Status', key: 'status', sortable: false },
      { title: 'Payment Method', key: 'paymentMethod' },
    
      // Dates
      { title: 'Issued', key: 'issuedDate' },
      { title: 'Due', key: 'dueDate' },
      { title: 'Paid At', key: 'paidAt' },
    
      // Internal / actions
      { title: 'Actions', key: 'actions', sortable: false },
    ])

    

    const perPage = ref(10)
    const totalInvoices = ref(0)
    const invoices = ref([]);
    const currentPage = ref(1)
    const perPageOptions = [10, 25, 50, 100]
    const searchQuery = ref('')
    const sortBy = ref('id')
    const orderBy = ref('id')
    const isSortDirDesc = ref(true)
    const statusFilter = ref(null)
    const agentRegistered = ref(false)

    // const alarmsDatas = computed(() => alarmData);
    const invoicesDatas = ref([]);
    const dataMeta = computed(() => {
    const localItemsCount = refInvoiceListTable.value ? refInvoiceListTable.value.localItems.length : 0
    return {
        from: perPage.value * (currentPage.value - 1) + (localItemsCount ? 1 : 0),
        to: perPage.value * (currentPage.value - 1) + localItemsCount,
        of: totalAlarms.value,
    }
    })

    const refetchData = () => {
        fetchAgents();
    }
    

    const fetchAgents = async () => {
        console.log('Fetching clients...', _invoiceStore);
        await _invoiceStore.fetchAgents({
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
            const { invoices, total } = response.data.value; // Adjust based on actual structure
      
            // Set the reactive variables
            invoicesDatas.value = invoices || []; // Populate alarmsDatas
            totalInvoices.value = total || 0; // Populate totalAlarms
      
            console.log('Clients Datas:', invoicesDatas.value); // Verify the value
          } else {
            console.error('No data found in response');
            invoicesDatas.value = []; // Reset alarmsDatas if no data
          }
        });
      };

      const registerAgentFromAdmin = async (agentData) => {       
        await _invoiceStore.registerAgentFromAdmin(agentData).then(response => {
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

      const blockUnblockUser = async (agentData) => {       
        await _invoiceStore.blockUnblockUser(agentData).then(response => {
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

      const deactivateUser = async (agentData) => {       
        await _userStore.deactivateUser(agentData).then(response => {
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
      
      const deleteAgentFromAdmin = async (agentId) => {
       
        await _userStore.deleteAgentFromAdmin(agentId).then(response => {
         
          // alert(response.success)
          if (response.status == 'success') {            
            $toast.success('Ky agjent u fshi me sukses!');
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
     
     
      watch([currentPage, perPage, searchQuery], () => {    
        console.log('search1', searchQuery.value);
        refetchData()         
      });
      
    
    return {
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
        
        invoices,
        refetchData,
        // Extra Filters
        invoicesDatas,
        registerAgentFromAdmin,
        deleteAgentFromAdmin,
        blockUnblockUser,
        deactivateUser,
        agentRegistered
      }
}
