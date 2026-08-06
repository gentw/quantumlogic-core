<script setup>
import ReportsLists from '@/views/admin/reports/ReportsLists.vue';
import TicketsLists from '@/views/admin/reports/tickets/List.vue';
import UsersList from '@/views/admin/reports/users/List.vue';
import * as feather from 'feather-icons';
const route = useRoute('admin-reports-tab')
const activeTab = computed({
  get: () => route.params.tab,
  set: () => route.params.tab,
})


// tabs
const tabs = [
  {
    title: 'Raporti i përdoruesve',
    icon: 'tabler-user-check',
    tab: 'users',
  },
  {
    title: 'Raporti i tiketave',
    icon: 'tabler-users',
    tab: 'tickets',
  },

  {
    title: 'Raporti i transaksioneve',
    icon: 'tabler-users',
    tab: 'transactions',
  },
 
]

const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};
</script>
<template>
  <div class="admin-clients-page">
  

  <div class="body">
    <ReportsLists />
    <div>
      <VTabs
      v-model="activeTab"
      class="v-tabs-pill my-2"
    >
        <VTab
          v-for="item in tabs"
          :key="item.icon"
          :value="item.tab"
          :to="{ name: 'admin-reports-tab', params: { tab: item.tab } }"
        >
          {{ item.title }}
        </VTab>

        <div>
          <VBtn
            variant="outlined"
            color="secondary"
            class="download-btn"
          >
          <i class="mr-2" v-html="renderFeatherIcon('external-link', 20)" />
            Shkarko        
          </VBtn>
        </div> <!-- end shkarko div -->
      </VTabs>

     

      <VWindow
        v-model="activeTab"
        class="disable-tab-transition"
        :touch="false"
      >
        <!-- Profile -->
        <VWindowItem value="users">
          <UsersList />
        </VWindowItem>

        <!-- Teams -->
        <VWindowItem value="tickets">
          <TicketsLists/>
        </VWindowItem>

       
      </VWindow>
     
    </div><!-- end div -->
   
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

.v-slide-group {
  margin: 0 !important;
  background: #f2f2f2;
  padding-block: 1.5rem !important;
  padding-inline: 0;
}

body .v-tabs.v-tabs-pill .v-slide-group-item--active.v-tab--selected.text-primary {
  background: transparent !important;
  box-shadow: none !important;
  color: rgb(var(--v-theme-on-primary)) !important;
  color: #c90707 !important;
}

.v-tabs.v-tabs-pill .v-tab.v-btn {
  color: #000;
  font-size: 1rem;
}

.v-tabs.v-tabs-pill .v-tab.v-btn:hover {
  background-color: transparent !important;
  box-shadow: none !important;
  color: #c90707 !important;
}

.download-btn {
  position: absolute;
  color: #000 !important;
  inset-inline-end: 0;

  i {
    color: #000;
    margin-block-start: 4px;
  }
}
</style>
