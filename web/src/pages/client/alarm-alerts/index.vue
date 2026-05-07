<script>
  import AlarmAlertsList from '@/views/client/alarm-alerts/alarm-alerts-list/AlarmAlertsList.vue';

  export default {
      components: {
        AlarmAlertsList,
      },
      setup() {
      
      }
  }
</script>

<template>
  <div class="alarm_alerts">
    <VCol cols="12">
      <VCard
        class="mb-6 alarm_alerts__heading"
        title="Alertet e alarmit"
      >
        <!-- <VCardText>Client</VCardText> -->
        
      </VCard>
    </VCol>
    <VCol cols="12">
      <AlarmAlertsList />
    </VCol>
  </div>
</template>

<style lang="scss">
.alarm_alerts {
  .v-card-item {
    padding: 0;
  }

  &__heading {
    background: transparent;

    .v-card-title {
      font-size: 1.3rem;
      font-weight: bold;
    }
  }
}

</style>
