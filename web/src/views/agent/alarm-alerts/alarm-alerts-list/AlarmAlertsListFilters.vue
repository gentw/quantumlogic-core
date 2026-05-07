<template>
  <VCard
      class="mb-6
      alarm_alerts__filters">
   <VCardText>
      <VRow>
        <VCol
          cols="2"
          md="2"
        >
          <div class="position-relative filterSection">
            <AppSelect
              :items="filterOptions"
              placeholder="Filter"
              v-model="selectedFilter"
              clearable
              clear-icon="tabler-x"
            />
            <VBadge
              class="newAlarmsBadge"
              :content="newAlarms"
              :offset-x="-18"
              :offset-y="6"
          ></VBadge>
          </div>
        </VCol>
        <VCol
          cols="3"
          md="3"
        >
        <div v-if="selectedFilter == 'status'">
          <AppSelect
            :items="_statusOptions"
            placeholder="Sipas Statusit"
            v-model="selectedStatus"
            clearable
            clear-icon="tabler-x"
          />
        </div>
        <div v-if="selectedFilter == 'date'">
          <AppDateTimePicker
            v-model="dateRange"
            placeholder="Sipas dates"
            :config="{ mode: 'range' }"
          />
        </div>

        </VCol>
        
        <VCol
          offset-md="3"
        >
          <AppTextField
            v-model="search"
            placeholder="Search ..."
            append-inner-icon="tabler-search"
            single-line
            hide-details
            dense
            outlined
          />
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
    statusFilter: {
      type: [String, null],
      default: null,
    },
    _statusOptions: {
      type: Array,
      required: true,
    },
    dateRange: {
      type: [String, null],
      required: true,
    },
    searchQuery: {
      type: [String, null],
      required: true,
    },
  },

  setup(props, { emit }) {
    // const emit = defineEmits(); 
    const newAlarms = ref(3);

    const filterOptions = ref([
        {
          title: 'Statusi',
          value: 'status',
        },
        {
          title: 'Data',
          value: 'date',
        }
      ])
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

    watch(() => search.value, (newValue) => {
        // ajax call when status is changed
        emit('update:searchQuery', newValue || '');
    }, { immediate: true });

    watch(() => dateRange.value, (newValue) => {
        // ajax call when status is changed
        emit('update:dateRange', newValue || '');
    }, { immediate: true });

    

    return {
      selectedStatus, selectedFilter, filterOptions, dateRange, newAlarms, search
    }
  }

}
</script>


<style lang="scss">
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
