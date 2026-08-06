<script setup>
import AddDomain from '@/views/client/domains/management/AddDomain.vue'
import VerifyDomain from '@/views/client/domains/management/VerifyDomain.vue'
import ProtectionStatus from '@/views/client/domains/management/ProtectionStatus.vue'

const route = useRoute('client-domains-management-tab')

const activeTab = computed({
  get: () => route.params.tab,
  set: () => route.params.tab,
})

// tabs
const tabs = [
  {
    title: 'Add Domain',
    icon: 'tabler-users',
    tab: 'add-domain',
  },
  {
    title: 'Verify Domain',
    icon: 'tabler-lock',
    tab: 'verify-domain',
  },
  {
    title: 'Protection Status',
    icon: 'tabler-file-text',
    tab: 'protection-status',
  },
]

definePage({ meta: { navActiveLink: 'client-domains-management-tab' } })
</script>

<template>
  <div>
    <VTabs
      v-model="activeTab"
      class="v-tabs-pill"
    >
      <VTab
        v-for="item in tabs"
        :key="item.icon"
        :value="item.tab"
        :to="{ name: 'client-domains-management-tab', params: { tab: item.tab } }"
      >
        <VIcon
          size="20"
          start
          :icon="item.icon"
        />
        {{ item.title }}
      </VTab>
    </VTabs>

    <VWindow
      v-model="activeTab"
      class="mt-6 disable-tab-transition"
      :touch="false"
    >
      <!-- AddDomain -->
      <VWindowItem value="add-domain">
        <AddDomain />
      </VWindowItem>

      <!-- VerifyDomain -->
      <VWindowItem value="verify-domain">
        <VerifyDomain />
      </VWindowItem>

      <!-- ProtectionStatus -->
      <VWindowItem value="protection-status">
        <ProtectionStatus />
      </VWindowItem>

    </VWindow>
  </div>
</template>
