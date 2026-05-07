<script>
  
import Details from '@/views/client/alarm-alerts/Details.vue';
import { onMounted } from 'vue';

  export default {
      components: {
        Details,
      },
      setup() {
        const route = useRoute('client-alarm-alerts-details-id')
        const alarmId = route.params.id;
        const titleAlarm = route.query.name;
        
        onMounted(() => {
           
          
        })

        return {alarmId,titleAlarm};
      
      }
  }
</script>

<template>
  
  <div class="alarm_alerts">
    <VCol cols="12">
      <VCard
        class="mb-6 alarm_alerts__heading"
        :title="titleAlarm"
      >
        <!-- <VCardText>Client</VCardText> -->
        
      </VCard>
    </VCol>
    <VCol cols="12">
      <Details v-model:alarm-id.sync="alarmId" />
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
