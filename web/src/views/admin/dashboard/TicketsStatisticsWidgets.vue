<script setup>
import * as feather from 'feather-icons';
const ticketsWidgetData = ref([
  {
    icon: 'check-square',
    color: 'success',
    title: 'Totali i tiketave',
    value: 42,
    change: 18.2,
    isHover: false,
    dotColor: 'orange'
  },
  {
    icon: 'check-square',
    color: 'success',
    title: 'Totali i tiketave të zgjidhura',
    value: 8,
    change: -8.7,
    isHover: false,
    dotColor: 'green'
  },
  {
    icon: 'check-square',
    color: 'success',
    title: 'Totali i tiketave të pacaktuara',
    value: 27,
    change: 4.3,
    isHover: false,
    dotColor: 'dark-blue'
  },
  {
    icon: 'check-square',
    color: 'success',
    title: 'Totali i tiketave në progres',
    value: 13,
    change: -2.5,
    isHover: false,
    dotColor: 'blue'
  },
  {
    icon: 'credit-card',
    color: 'info',
    title: 'Totali i faturave të paguara',
    value: 13,
    change: -2.5,
    isHover: false,
    dotColor: 'green'
  },
  {
    icon: 'credit-card',
    color: 'info',
    title: 'Faturat totale në pritje',
    value: 13,
    change: -2.5,
    isHover: false,
    dotColor: 'blue'
  },

])


const ticketsWidgetData1 = ref([
  {
    icon: 'users',
    color: 'primary',
    title: 'Numri i klienteve',
    value: 500,
    change: 18.2,
    isHover: false,
  },
  {
    icon: 'shopping-bag',
    color: 'warning',
    title: 'Numri i agjenteve',
    value: 80,
    change: -8.7,
    isHover: false,
  },
  {
    icon: 'credit-card',
    color: 'info',
    title: 'Totali Faturave',
    value: 16,
    change: 4.3,
    isHover: false,
    dotColor: 'orange'
  },

])

const renderFeatherIcon = (iconName) => {
  if (feather.icons[iconName]) {
    return feather.icons[iconName].toSvg();
  }
  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

const firstTwoTickets = computed(() => ticketsWidgetData1.value.slice(0, 2));
const remainingTickets = computed(() => ticketsWidgetData1.value.slice(2));
</script>

<template>
  <VRow class="tickets-statistics-widgets">
    <VCol
      cols="12"
      md="4"
      sm="12"
    >
    <!-- row-->
    <VRow>
        <span class="firstTwoTickets">
          <VCol
            
            v-for="(data, index) in firstTwoTickets"
            :key="index"
            cols="12"
            md="12"
            sm="12"
            class="firstTwoTickets--col"
          >
            <div>
              <VCard
                class="logistics-card-statistics cursor-pointer"
                :style="data.isHover ? `border-block-end-color: rgb(var(--v-theme-${data.color}))` : `border-block-end-color: rgba(var(--v-theme-${data.color}),0.38)`"
                @mouseenter="data.isHover = true"
                @mouseleave="data.isHover = false"
              >
                <VCardText class="vcard-widget d-flex justify-space-between" style="align-items: center;">
                  <div class="d-flex gap-x-4 mb-1 flex-column">
                    <VAvatar
                      :color="data.color"
                      rounded
                      size="60"
                    >
                      <i v-if="data.icon" v-html="renderFeatherIcon(data.icon)" />
                    </VAvatar>
                    <div class="d-flex flex-column mt-2">
                      <div class="text-body-1 text-capitalize font-weight-bold">
                            {{ data.title }}
                        </div>

                        <h4 class="text-h4">
                          {{ data.value }}
                        </h4>
                    </div>
                  </div>

                  <div>
                    <RouterLink to="#" class="text-default">
                    <i style="color: #fff;" v-html="renderFeatherIcon('chevron-right')" />
                    </RouterLink>
                  </div>
                 
                </VCardText>
                
              </VCard>
            </div>
          </VCol>
        </span>

        <VCol
          v-for="(data, index) in remainingTickets"
          :key="index"
          cols="12"
          md="12"
          sm="12"
        >
          <div>
            
            <VCard
              class="logistics-card-statistics cursor-pointer"
              :style="data.isHover ? `border-block-end-color: rgb(var(--v-theme-${data.color}))` : `border-block-end-color: rgba(var(--v-theme-${data.color}),0.38)`"
              @mouseenter="data.isHover = true"
              @mouseleave="data.isHover = false"
            >
              <div class="dot blue"><span><span></span></span></div>
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
                    Shiko të gjitha
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
    <!-- ./row-->
    </VCol>
    <VCol
      cols="12"
      md="8"
      sm="12"
    >
      <VRow>
        <VCol
          v-for="(data, index) in ticketsWidgetData"
          :key="index"
          cols="12"
          md="6"
          sm="6"
        >
          <div>
            <VCard
              class="logistics-card-statistics cursor-pointer"
              :style="data.isHover ? `border-block-end-color: rgb(var(--v-theme-${data.color}))` : `border-block-end-color: rgba(var(--v-theme-${data.color}),0.38)`"
              @mouseenter="data.isHover = true"
              @mouseleave="data.isHover = false"
            >
              <div :class="'dot ' + data.dotColor"><span><span></span></span></div>
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
                    Shiko të gjitha
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
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
@use "@core/scss/base/mixins" as mixins;

.logistics-card-statistics {
  border-radius: 7px !important;
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

.firstTwoTickets {
  padding: 0;
  border-radius: 7px;
  margin: 0;
  margin: 12px;
  background-color: #f12327;
  inline-size: 100%;
  padding-block: 13px;
  padding-inline: 0;

  .text-body-1,
  .text-h4 {
    color: #fff;
  }

  .v-card {
    border: none !important;
    background: transparent;

    .v-avatar {
      background: #fff !important;
      color: #f12327 !important;
    }

    &:hover {
      border: none !important;
    }
  }

  &--col {
    padding: 0;
    margin: 0;

    &:nth-child(1) {
      position: relative;

      &::after {
        position: absolute;
        background-color: #fff;
        block-size: 1px;
        content: "";
        inline-size: 90%;
        inset-inline: 0;
        inset-inline-start: 50%;
        transform: translateX(-50%);
      }
    }
  }
}

@media (min-width: 992px) {
  .v-card {
    block-size: 170px !important;
  }
}

//PULSING DOTS

// variables
$blue: #26b3ff;
$blue-outer: rgba(86, 136, 252, 100%);
$green: #35e861;
$green-outer: rgba(96, 219, 132, 100%);
$red: #ff4b44;
$red-outer: rgba(232, 111, 112, 40%);
$orange: #ea9735;
$orange-outer: rgba(255, 183, 90, 40%);
$dark-blue: #394155;
$dark-blue-outer: rgba(57, 65, 85, 20%);

// animations
@mixin keyframes($name, $max-size) {
  @keyframes pulse-#{$name} {
    0% {
      opacity: 0.75;
      transform: scale(1);
    }

    25% {
      opacity: 0.75;
      transform: scale(1);
    }

    100% {
      opacity: 0;
      transform: scale($max-size);
    }
  }
}

@include keyframes("2-5", 2.5);

.dot {
  position: absolute;
  border-radius: 50%;
  block-size: 10px;
  inline-size: 10px;
  inset-block-start: 15px;
  inset-inline-end: 15px;

  > span {
    display: block;
    border-radius: 50%;
    animation: pulse-2-5 2s linear infinite;
    block-size: 12px;
    inline-size: 12px;
    margin-block-start: -1px;
    margin-inline-start: -1px;

    > span {
      display: block;
      border-radius: 50%;
      animation: pulse-2-5 2s linear infinite;
      block-size: 12px;
      inline-size: 12px;

      &::after {
        display: block;
        border-radius: 50%;
        animation: pulse-2-5 2s linear infinite;
        block-size: 12px;
        content: "";
        inline-size: 12px;
      }
    }
  }

  &.blue {
    background-color: $blue;

    span {
      background-color: $blue-outer;

      &::after {
        background-color: $blue-outer;
      }
    }
  }

  &.green {
    background-color: $green;

    span {
      background-color: $green-outer;

      &::after {
        background-color: $green-outer;
      }
    }
  }

  &.red {
    background-color: $red;

    span {
      background-color: $red-outer;

      &::after {
        background-color: $red-outer;
      }
    }
  }

  &.orange {
    background-color: $orange;

    span {
      background-color: $orange-outer;

      &::after {
        background-color: $orange-outer;
      }
    }
  }

  &.dark-blue {
    background-color: $dark-blue;

    span {
      background-color: $dark-blue-outer;

      &::after {
        background-color: $dark-blue-outer;
      }
    }
  }
}
</style>
