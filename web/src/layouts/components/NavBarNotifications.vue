<script setup>

const emit = defineEmits(['update:showNotificationsOverlay'])

const props = defineProps({
  showNotificationsOverlay: {
    type: [Boolean, false],
    default: false
  },
})

const notifications = ref([
 
])


const markRead = notificationId => {
  notifications.value.forEach(item => {
    notificationId.forEach(id => {
      if (id === item.id)
        item.read = true
    })
  })
}

const markUnRead = notificationId => {
  notifications.value.forEach(item => {
    notificationId.forEach(id => {
      if (id === item.id)
        item.read = false
    })
  })
}

const handleNotificationClick = notification => {
  if (!notification.read)
    markRead([notification.id])
}

const showNotificationsOverlay = ref(false);

watch(() => showNotificationsOverlay.value, (newValue) => {
    emit('update:showNotificationsOverlay', newValue);
}, { immediate: true });
</script>

<template>
  <!--  -->

  <Notifications
    v-model:showNotificationsOverlay.sync="showNotificationsOverlay"
    v-model:notifications.sync="notifications"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>

<style type="scss">

</style>
