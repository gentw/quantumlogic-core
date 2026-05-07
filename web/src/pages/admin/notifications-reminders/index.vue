<script setup>
import NotificationsList from '@/views/admin/notifications-reminders/NotificationsList.vue';
</script>
<template>
  <div class="admin-clients-page">
    <div class="body">
      <NotificationsList />
    </div>
  </div>
</template>
<style type="scss" scoped>
.header .v-card-item {
  padding-block-end: 0;
}

.content .text-h4 {
  font-size: 1.3rem !important;
  font-weight: bold !important;
}

#clients-list {
  .clients-list-actions {
    inline-size: 8rem;
  }

  .clients-list-filter {
    inline-size: 12rem;
  }
}
</style>
