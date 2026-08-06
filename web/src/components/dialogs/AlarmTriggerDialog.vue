<script setup>
import useAlarmAlertsList from '@/views/client/alarm-alerts/alarm-alerts-list/useAlarmAlertsList';
import * as feather from 'feather-icons';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
import { VCol } from 'vuetify/lib/components/index.mjs';
const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  alarmId: {
    type: Number, 
    required: true,
  },

});

const _alarmId = computed(() => props.alarmId);

const isDialogVisible = ref(props.modelValue);
// const isDialogVisible = ref(false)

const $toast = useToast();

const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

watch(
  () => props.modelValue,
  (newValue) => {
    isDialogVisible.value = newValue;
  }
);
const emit = defineEmits(['update:modelValue']);
const closeDialog = (event) => {
  const itemValue = event.currentTarget.getAttribute('data-item');

  emit('update:modelValue', !props.modelValue);
  

  respondToAlarm(_alarmId.value, {
    "response": itemValue
  }).then(()=> {
    if(respondToAlarmStatus.value) {
      $toast.success('Pergjigjja juaj kaloi tek agjenti.');

      // Force dismiss specific toast
      // instance.dismiss();

      // // Dismiss all opened toast immediately
      // $toast.clear();
    }
  });

  
  
}

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
  respondToAlarm,
  respondToAlarmStatus
} = useAlarmAlertsList()
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    max-width="500"
    class="alarm-trigger-dialog"
  >
    <!-- Dialog Activator -->
    <!-- <template #activator="{ props }">
      <VBtn v-bind="props">
        Open Dialog
      </VBtn>
    </template> -->

    <!-- Dialog close btn -->
    <!-- <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" /> -->

    <!-- Dialog Content -->
    <VCard>
      
      <template #title>
        <h2 class="mb-5 text-center">Alarmi në shtëpi</h2>
      </template>

      <VDivider></VDivider>
      <VCardText>
        <VRow>
          <VCol
          cols="12"
          class="d-flex justify-center mb-4"
          >
            <i class="alert-icon" v-html="renderFeatherIcon('alert-octagon', 90)" />
          </VCol>

          <VCol
            cols="12"
            class="mb-4"
          >
            <h3>Sistemi i juaj është aktivizuar ju lutem konfirmoni nëse e keni aktivizuar alarmin</h3>
          </VCol>
         
          
        </VRow>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          
          color="primary"
          @click="closeDialog($event)"
          class="alarm-btn"
          data-item="not_me"
        >
          Nuk jam unë        
        </VBtn>
        <VBtn @click="closeDialog($event)"
        variant="tonal"
        color="secondary"
        class="alarm-btn secondary-btn"
        data-item="is_me"
        >
          Jam unë
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.alarm-trigger-dialog .feather {
  color: #f02227; /* Ensures the icon inherits text color */
  text-align: center;
}

.alarm-trigger-dialog .alarm-btn {
  position: relative;
  border-radius: 0 !important;
  block-size: 50px !important;
  font-weight: bold !important;
  inline-size: 48.5%;
}

.alarm-trigger-dialog .secondary-btn {
  background: #2e2e2e;
  color: #fff !important;
}

@media (max-width: 768px) {
  .alarm-trigger-dialog .alarm-btn {
    inline-size: 100%;
  }
}
</style>
