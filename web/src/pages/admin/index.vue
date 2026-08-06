<script>
import TicketsStatisticsWidget from '@/views/admin/dashboard/TicketsStatisticsWidgets.vue';
import * as feather from 'feather-icons';
export default {
  components: {
    TicketsStatisticsWidget
  },
  setup() {
onMounted(()=>{
});



    const renderFeatherIcon = (iconName) => {
      if (feather.icons[iconName]) {
        return feather.icons[iconName].toSvg();
      }
      // Return default or fallback icon if necessary
      return feather.icons['alert-circle'].toSvg(); // Default fallback icon
    };

    return {
      renderFeatherIcon,
    };
  },
};

</script>

<template>
  <div class="home-page">
   

    <div class="content">
      <TicketsStatisticsWidget/>
    </div>
    



    <div>
      
    </div>
  </div>
</template>
<style type="scss">
.sc-header--img {
  inline-size: 82px;
}

.sc-launcher .sc-closed-icon[data-v-13c85e18],
.sc-launcher .sc-open-icon[data-v-13c85e18] {
  position: fixed;
  block-size: 80px;

  /* inset-block-end: 70px !important; */
  inline-size: 80px;
  inset-block-end: 60px;
  inset-inline-end: 15px;
}

.sc-launcher[data-v-13c85e18] {
  inset-block-end: 70px;
}

.sc-chat-window[data-v-89ac169e] {
  inset-block-end: 65px;
}

.sc-message--text .sc-message--text-content[data-v-784eb559] {
  margin-block-end: 0.3rem;
  white-space: pre-wrap;
}

.sc-user-input--file-icon-wrapper,
.sc-user-input--picker-wrapper {
  display: none;
}

/*** next part **/
.header .v-card-item {
  padding-block-end: 0;
}

.content .text-h4 {
  font-size: 1.3rem !important;
  font-weigth: bold !important;
  line-height: 1.375rem !important;
}

.layout-wrapper.layout-nav-type-vertical .layout-content-wrapper {
  background: transparent;
}

.pay-online {
  border-radius: 50px;
  background-color: #4ade80 !important;
  box-shadow: none !important;
  font-size: 1rem;
  font-weight: bold !important;
}

@media (max-width: 768px) {
  .pay-online {
    margin-block-start: 25px;
    margin-inline-start: 25px;
  }
}
</style>
