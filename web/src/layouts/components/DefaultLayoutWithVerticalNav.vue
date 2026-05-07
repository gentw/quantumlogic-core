<script setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'
import Pusher from 'pusher-js'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'
// @layouts plugin
import AlarmTriggerDialog from '@/components/dialogs/AlarmTriggerDialog.vue'
import { VerticalNavLayout } from '@layouts'
import { onMounted } from 'vue'

// SECTION: Loading Indicator
const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref(null)

const isDialogVisible = ref(false);

const alarmId = ref(0);
onBeforeMount(()=> {
  // alert(1)
  subscribe()
})
onMounted(() => {
})
watch([
  isFallbackStateActive,
  refLoadingIndicator,
], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()
  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })
// !SECTION

const subscribe = () => {
  let pusher = new Pusher('f4b17e21601abcc09c58', { cluster: 'eu' })

  let userId = useCookie("userData").value.id;
  pusher.subscribe(`client-alarms.${userId}`);

  pusher.bind('new-incoming-alarm-for-client', data => {
    console.log("ALARM ID", data.alarmData.id);
    alarmId.value = data.alarmData.id;
    
    // alarmId.value = 16;
    // alarmId.value = 42; //should be set by this pusher data
    isDialogVisible.value = true;
  })
}

const showNotificationsOverlay = ref(false);

// const showOverlay = () => {
//   showNotificationsOverlay.value = true
// }
</script>

<template>
  <AlarmTriggerDialog v-model:alarm-id.sync="alarmId" v-model="isDialogVisible"/>
  <div class="notificationOverlay" 
    v-if="showNotificationsOverlay"
    style=" position: absolute;
  z-index: 1004;background: #000; block-size: 100%; inline-size: 100%; inset-block-start: 100px; opacity: 0.5;"></div>
  <VerticalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavBarNotifications 
        v-model:showNotificationsOverlay.sync="showNotificationsOverlay"
         class="me-1" />
         <div class="vertical-line"></div>
        <UserProfile />
      </div>
    </template>

    <AppLoadingIndicator ref="refLoadingIndicator" />


    <!-- 👉 Pages -->
    <RouterView v-slot="{ Component }">
      <Suspense
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <Component :is="Component" />
      </Suspense>
    </RouterView>

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>
<style type="scss">
  .vertical-line {
    background-color: #e5e7eb;
    block-size: 20px;
    inline-size: 1px;
    margin-block: 0;
    margin-inline: 20px;
  }
</style>
