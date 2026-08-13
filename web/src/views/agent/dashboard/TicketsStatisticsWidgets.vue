<script setup>
import * as feather from 'feather-icons';
const ticketsWidgetData = ref([
  {
    icon: 'check-square',
    color: 'secondary',
    title: 'Tiketat e reja',
    value: 42,
    change: 18.2,
    isHover: false,
  },
  {
    icon: 'check-square',
    color: 'success',
    title: 'Tiketat e përfunduara',
    value: 8,
    change: -8.7,
    isHover: false,
  },
  {
    icon: 'check-square',
    color: 'info',
    title: 'Tiketat Totale',
    value: 27,
    change: 4.3,
    isHover: false,
  },

])

const renderFeatherIcon = (iconName) => {
  if (feather.icons[iconName]) {
    return feather.icons[iconName].toSvg();
  }
  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};
</script>

<template>
  <VRow class="tickets-statistics-widgets">
    <VCol
      v-for="(data, index) in ticketsWidgetData"
      :key="index"
      cols="12"
      md="4"
      sm="6"
    >
      <div>
        <VCard
          class="logistics-card-statistics cursor-pointer"
          :style="data.isHover ? `border-block-end-color: rgb(var(--v-theme-${data.color}))` : `border-block-end-color: rgba(var(--v-theme-${data.color}),0.38)`"
          @mouseenter="data.isHover = true"
          @mouseleave="data.isHover = false"
        >
          <VCardText class="vcard-widget">
            <div class="d-flex align-center gap-x-4 mb-1">
              <VAvatar
                :color="data.color"
                rounded
                size="60"
              >
                <i v-if="data.icon" v-html="renderFeatherIcon(data.icon)" />
              </VAvatar>
              <div class="d-flex flex-column">
                <div class="text-body-1 text-capitalize font-weight-bold">
                      {{ data.title }}
                  </div>

                  <h4 class="text-h4">
                    {{ data.value }}
                  </h4>
              </div>
            </div>
            
          </VCardText>
          <VCardText class="see-all">
            <div class="d-flex justify-space-between see-all--content">
              <div class="text-body-1 font-weight-bold text-default">
                {{ $t('common.viewAll') }}
               </div>
              <div>
                <RouterLink to="#" class="text-default">
                <i v-html="renderFeatherIcon('chevron-right')" />
                </RouterLink>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
@use "@core/scss/base/mixins" as mixins;

.logistics-card-statistics {
  border-block-end-style: solid;
  border-block-end-width: 2px;

  &:hover {
    border-block-end-width: 3px;
    margin-block-end: -1px;

    @include mixins.elevation(8);

    transition: all 0.1s ease-out;
  }
}

.skin--bordered {
  .logistics-card-statistics {
    border-block-end-width: 2px;

    &:hover {
      border-block-end-width: 3px;
      margin-block-end: -2px;
      transition: all 0.1s ease-out;
    }
  }
}

.tickets-statistics-widget {
  .v-avatar {
    block-size: 50px !important;
    inline-size: 50px !important;
  }
}

.see-all {
  background: #f9fafb;
  padding-block: 10px;

  &--content {
    align-items: center !important;
  }

  .text-default {
    font-size: 0.99rem !important;
  }
}

.vcard-widget {
  padding-block: 30px;
}
</style>
