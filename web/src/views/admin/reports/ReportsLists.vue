<script setup>
import * as feather from 'feather-icons';
import ReportStatisticsWidgets from './includes/ReportStatisticsWidgets.vue';
const dateRange = ref(null);

const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

// const totalClients = computed(() => clientsDatas.value.totalClients)

</script>
<template>
  <!-- <section v-if="clients"> -->
    <section>
    <VCard id="clients-list"
      title="Raportet"

    >
     
      <VCardText class="pb-0">
        <div class="d-flex flex-row justify-space-between">
          <div>Menaxhimi i raporteve</div>
          <div class="d-flex">
            <AppDateTimePicker
              v-model="dateRange"
              placeholder="Sipas dates"
              :config="{ mode: 'range' }"
            >
            <template v-slot:prepend>
              <i class="mr-2" v-html="renderFeatherIcon('calendar', 20)" />
            </template>
            </AppDateTimePicker>
            <div class="ml-2">
              <VBtn
                  variant="outlined"
                  color="secondary"
                  @click="(filterUsers) ? filterUsers = 0 : filterUsers = 1"
                >
                <i v-html="renderFeatherIcon('sliders', 18)" />                  
                </VBtn>
            </div>
          </div>
          
        </div>

        <ReportStatisticsWidgets class="mt-5 mb-5" />

      </VCardText>
      <VDivider />

    <!-- !SECTION -->
    </VCard>
    
  </section>
  <!-- <section v-else>
    <VCard>
      <VCardTitle>Nuk u gjet asnje klient!</VCardTitle>
    </VCard>
  </section> -->
</template>
<style type="scss">
#clients-list .v-btn--icon {
  border: 1px solid rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  border-radius: 5px;
  background: #f4f4f4;
  margin-block: 10px;
  margin-inline: 5px;
}

#clients-list .clients__filters--text {
  padding-inline: 0 !important;
}

#clients-list .v-field {
  border-radius: 5px;
}

#clients-list .v-field__field {
  padding-block: 4px !important;
  padding-inline: 0;
}

#clients-list .v-input .v-field .v-field__input::placeholder {
  color: #374151 !important;
  font-size: 0.95rem;
}

#clients-list .v-input.v-input--density-comfortable .v-field .v-field__clearable > .v-icon {
  inline-size: 1.5rem;
}

#clients-lis .v-input.v-input--density-comfortable .v-field .v-field__append-inner > .v-icon {
  font-size: 1.4rem;
}

#clients-list .v-card-item {
  padding-block-end: 0 !important;
}

#clients-list .v-btn.v-btn--density-default {
  border-color: #d0d0d0 !important;
  border-radius: 3px;
  block-size: calc(var(--v-btn-height) + 8px);
  color: #000;
}

#clients-list .feather {
  display: inline-block;
  color: #000;
  margin-block-start: 8px;
  transform: rotate(-90deg);
}

#clients-list .v-data-table__td {
  color: #374151;
}

.add-user-btn {
  position: absolute;
  background-color: #d81b1b !important;
  color: #fff !important;
  inset-inline-end: 50px;
}

#clients-list .app-picker-field {
  /* inline-size: 25%; */
}

#clients-list .app-picker-field i {
  position: absolute;
  z-index: 9;
  margin-inline-start: 220px;
  transform: rotate(91deg);
}

#clients-list .app-picker-field .v-input__control {
  inline-size: 250px;
}

#clients-list .v-btn--size-default {
  min-inline-size: 40px !important;
}

#clients-list .flat-picker-custom-style {
  background-color: #fafafa;
  inline-size: 20px;
  min-inline-size: 20px;
}
</style>
