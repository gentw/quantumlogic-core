<template>
  <VCard
      class="mb-6
      alarm_alerts__filters">
   <VCardText class="pl-0">
    <div>{{ $t('users.filterBy') }}</div>
      <VRow>
        <VCol
          cols="3"
          md="3"
        >
          <div>
            <AppSelect
              :items="_statusOptions"
              :placeholder="$t('users.byStatus')"
              v-model="selectedStatus"
              clearable
              clear-icon="tabler-x"
            />
          </div>
        </VCol>
        <VCol
          cols="3"
          md="3"
        >
        
       

        </VCol>
        
        <VCol
          offset-md="3"
        >
        
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>


<script>
import { ref } from 'vue';
const search = ref("");``
const dateRange = ref('')

export default {
  
  props: {
    _statusOptions: {
      type: Array,
      required: true,
    },
    statusFilter: {
      type: [String, null],
      default: null,
    },
   
  },

  setup(props, { emit }) {
    // const emit = defineEmits(); 
   
    // UnRegister on leave
    onUnmounted(() => { 
     
    })

    onMounted(() => {    
    });

    const selectedStatus = ref()
    const selectedFilter = ref()
    
    watch(() => selectedStatus.value, (newValue) => {
        // ajax call when status is changed
        // emit('update:statusFilter', newValue);
        emit('update:statusFilter', newValue || '');
    }, { immediate: true });

    // watch(() => search.value, (newValue) => {
    //     // ajax call when status is changed
    //     emit('update:searchQuery', newValue || '');
    // }, { immediate: true });

    // watch(() => dateRange.value, (newValue) => {
    //     // ajax call when status is changed
    //     emit('update:dateRange', newValue || '');
    // }, { immediate: true });

    

    return {
      selectedStatus
    }
  }

}
</script>


<style lang="scss" scoped>
.alarm_alerts {
  &__filters {
    margin-block-end: 0 !important;

    .v-card-item {
      padding: 0;
    }

    .v-card-title {
      font-size: 1.3rem;
      font-weight: bold;
    }

    .v-field {
      border-radius: 0;
    }

    .v-field__input {
      border: 1px color #eee !important;
      block-size: 48px;
      color: #374151;
      font-size: 0.97rem;

      &::placeholder {
        color: #374151 !important; /* Change this to your desired color */
        opacity: 1; /* Optional: Ensures full opacity of the color */
      }

      input {
        &::placeholder {
          color: #374151 !important; /* Change this to your desired color */
          opacity: 1; /* Optional: Ensures full opacity of the color */
        }
      }
    }
  }
}

.newAlarmsBadge {
  position: absolute;
  inset-block-start: 17px;
  inset-inline-end: 66px;
}

.filterSection {
  inline-size: 135px;
}
</style>
