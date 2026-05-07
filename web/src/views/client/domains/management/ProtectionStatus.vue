<script setup>
import avatar1 from '@images/avatars/avatar-1.png'
import LatestLogs from '@/views/client/domains/management/protection_status/LatestLogs.vue';

const domainData = {
  name: ''
}


const refInputEl = ref()
const isConfirmDialogOpen = ref(false)
const domainDataLocal = ref(structuredClone(domainData))
const isAccountDeactivated = ref(false)
const validateAccountDeactivation = [v => !!v || 'Please confirm account deactivation']

const resetForm = () => {
  domainData.value = structuredClone(domainData)
}

const statistics = [
  {
    title: 'Blocked Attacks',
    stats: '3,842',
    icon: 'tabler-shield-lock',
    color: 'error',
  },
  {
    title: 'Requests Analyzed',
    stats: '246,918',
    icon: 'tabler-activity',
    color: 'primary',
  },
  {
    title: 'Threats Detected',
    stats: '712',
    icon: 'tabler-bug',
    color: 'warning',
  },
  {
    title: 'Protection Uptime',
    stats: '99.98%',
    icon: 'tabler-clock-check',
    color: 'success',
  },
]


const protectionFeatures = ref([
  {
    key: 'edge_firewall',
    icon: 'tabler-shield-lock',
    title: 'Edge Firewall',
    text: 'Blocks malicious traffic before reaching your infrastructure',
    enabled: true,
  },
  {
    key: 'ddos',
    icon: 'tabler-radar',
    title: 'DDoS Protection',
    text: 'Layer 3–7 protection with adaptive rate limiting',
    enabled: true,
  },
  {
    key: 'waf',
    icon: 'tabler-shield-code',
    title: 'Web Application Firewall (WAF)',
    text: 'OWASP Top 10 and bot attack prevention',
    enabled: false,
  },
  {
    key: 'php_bypass',
    icon: 'tabler-bolt',
    title: 'PHP Bypass',
    text: 'Short-circuit requests before PHP execution',
    enabled: true,
  },
  {
    key: 'monitoring',
    icon: 'tabler-activity-heartbeat',
    title: 'Runtime Monitoring',
    text: 'Live metrics and anomaly detection',
    enabled: false,
  },
])

const agents = ref([
  {
    key: 'wordpress',
    icon: 'tabler-brand-wordpress',
    title: 'WordPress',
    text: 'Plugin agent – connects WordPress to SentriGate Core',
    installed: false,
    downloadUrl: '/agents/wordpress.zip',
  },
  {
    key: 'laravel',
    icon: 'tabler-brand-laravel',
    title: 'Laravel',
    text: 'Composer package with middleware & events',
    installed: true,
    downloadUrl: '/agents/laravel.tar.gz',
  },
  {
    key: 'symfony',
    icon: 'tabler-brand-symfony',
    title: 'Symfony',
    text: 'Bundle with kernel listeners',
    installed: false,
    downloadUrl: '/agents/symfony.tar.gz',
  },
  {
    key: 'nodejs',
    icon: 'tabler-brand-nodejs',
    title: 'Node.js',
    text: 'Express / Fastify middleware agent',
    installed: false,
    downloadUrl: '/agents/nodejs.tar.gz',
  },
  {
    key: 'php',
    icon: 'tabler-brand-php',
    title: 'Generic PHP',
    text: 'auto_prepend_file based protection',
    installed: true,
    downloadUrl: '/agents/php-agent.tar.gz',
  },
  {
    key: 'nginx',
    icon: 'tabler-server',
    title: 'Nginx / OpenResty',
    text: 'Lua-based edge agent for PHP bypass',
    installed: false,
    downloadUrl: '/agents/nginx-agent.tar.gz',
  },
])

const protection = ref(true);

const origin_configured = ref(false)

const originSettings = ref({
  protocol: 'https',
  port: 443,
  tlsMode: 'full_strict',
  lbMode: 'disabled',
  origins: [
    { ip: '185.45.12.88', healthy: true },
  ],
})

const canAddOrigin = computed(() => {
  if (originSettings.value.lbMode === 'disabled') {
    return originSettings.value.origins.length < 1
  }

  return originSettings.value.origins.length < 3
})

</script>

<template>
    <VRow class="match-height">
    <!-- 👉 Congratulation John -->
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <VCard>
        <VRow no-gutters>
          <VCol cols="12">
            <VCardText>
            <div class="d-flex align-center mb-2">
                <VIcon
                  icon="tabler-world"
                  color="success"
                  size="20"
                  class="me-2"
                />
                <h5 class="text-h5 text-no-wrap mb-0">
                  Domain: <span class="font-weight-medium">www.bitemybytes.com</span>
                </h5>
              </div>
              <!-- Status -->
              <div class="d-flex align-center mb-2">
                <VIcon
                  icon="tabler-circle-check"
                  color="success"
                  size="20"
                  class="me-2"
                />
                <h5 class="text-h5 text-no-wrap mb-0">
                  Status: <span class="text-success">Verified & Connected</span>
                </h5>
              </div>

              <!-- Protection -->
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-shield-check"
                  :color="protection ? 'success' : 'warning'"
                  size="20"
                  class="me-2"
                />
                <h5 class="text-h5 text-no-wrap mb-0">
                  Protection:
                </h5>

                <VChip
                  class="ms-2"
                  :color="protection ? 'success' : 'warning'"
                  size="small"
                  label
                >
                  {{ protection ? 'Active' : 'Disabled' }}
                </VChip>


              </div>

              <!-- Protection -->
              <div class="d-flex align-center mt-2">
                <VIcon
                  icon="tabler-server"
                  :color="origin_configured ? 'success' : 'error'"
                  size="20"
                  class="me-2"
                />
                <h5 class="text-h5 text-no-wrap mb-0">
                  Origin IP:
                </h5>

                <VChip
                  class="ms-2"
                  :color="origin_configured ? 'success' : 'error'"
                  size="small"
                  label
                >
                  {{ origin_configured ? 'Active' : 'Not Configured' }}
                </VChip>


              </div>

              <div class="mt-4">
                <VSwitch
                  v-model="protection"
                  label="Protection"
                  density="compact"
                />
              </div>

              <div class="mt-4">
                <div class="d-flex" style="justify-content: space-between; width: 100%; position:relative; ">
                  <div>
                    <VSwitch
                      v-model="development_mode"
                      label="Dev Mode"
                      density="compact"
                    />
                  </div>

                  <div>
                    <VBtn
                      size="small"
                    >
                      Purge Cache
                    </VBtn>
                  </div>
                </div>                
              </div>
            </VCardText>
          </VCol>
        </VRow>
      </VCard>
    </VCol>

    <!-- 👉 Ecommerce Transition -->
    <VCol
      cols="12"
      md="7"
      lg="8"
    >
       <VCard title="Statistics" class="h-100">
        <template #append>
          <span class="text-sm text-disabled">Updated 1 month ago</span>
        </template>

        <VCardText>
          <VRow>
            <VCol
              v-for="item in statistics"
              :key="item.title"
              cols="6"
              md="3"
            >
              <div class="d-flex align-center gap-4 mt-md-9 mt-0">
                <VAvatar
                  :color="item.color"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon :icon="item.icon" />
                </VAvatar>

                <div class="d-flex flex-column">
                  <h5 class="text-h5">
                    {{ item.stats }}
                  </h5>
                  <div class="text-sm">
                    {{ item.title }}
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VCard
      >
      <!-- Title + Subtitle with right-aligned button -->
      <template #title>
        <div class="d-flex justify-space-between align-center w-100">
          <!-- Left: Title + Subtitle -->
          <div>
            <div class="v-card-title">Origin & Load Balancing</div>
            <div class="v-card-subtitle">Configure multiple origins, load balancing and TLS mode</div>
          </div>

          <!-- Right: Button -->
          <VBtn color="primary" variant="outlined" small>
            I need hosting assistance
          </VBtn>
        </div>
      </template>
        <VCardText>
          <!-- Load Balancing & TLS -->
          <VRow>
            <VCol cols="12" md="4">
              <VSelect
                v-model="originSettings.lbMode"
                label="Load Balancing Mode"
                prepend-inner-icon="tabler-arrows-shuffle"
                :items="[
                  { title: 'Disabled', value: 'disabled' },
                  { title: 'Round Robin', value: 'round_robin' },
                  { title: 'Failover (Primary → Backup)', value: 'failover' },
                ]"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VSelect
                v-model="originSettings.tlsMode"
                label="TLS Mode"
                prepend-inner-icon="tabler-shield-lock"
                :items="[
                  { title: 'Full', value: 'full' },
                  { title: 'Full (Strict)', value: 'full_strict' },
                ]"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                v-model="originSettings.port"
                label="Origin Port"
                type="number"
                prepend-inner-icon="tabler-plug"
              />
            </VCol>
          </VRow>

          <!-- Origin IP Pool -->
          <VDivider class="my-4" />

          <h6 class="text-h6 mb-3">Origin Pool</h6>

          <VRow
            v-for="(origin, index) in originSettings.origins"
            :key="index"
            class="mb-2"
          >
            <VCol cols="12" md="6">
              <VTextField
                v-model="origin.ip"
                label="Origin IP / Host"
                prepend-inner-icon="tabler-server"
              />
            </VCol>

            <VCol cols="6" md="3" class="d-flex align-center">
              <VChip
                size="small"
                :color="origin.healthy ? 'success' : 'error'"
                label
              >
                {{ origin.healthy ? 'Healthy' : 'Unhealthy' }}
              </VChip>
            </VCol>

            <VCol cols="6" md="3" class="d-flex justify-end">
              <VBtn
                icon
                variant="text"
                color="error"
                @click="originSettings.origins.splice(index, 1)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </VCol>
          </VRow>

          <VBtn
            variant="tonal"
            color="primary"
            class="mt-2"
            :disabled="!canAddOrigin"
            @click="originSettings.origins.push({ ip: '', healthy: false })"
          >
            Add Origin
          </VBtn>

          <!-- Save -->
          <VRow class="mt-6">
            <VCol cols="12" class="d-flex justify-end">
              <VBtn
                color="primary"
              >
                Save Configuration
              </VBtn>
            </VCol>
          </VRow>

          <!-- Info -->
          <VAlert
            type="info"
            variant="tonal"
            icon="tabler-info-circle"
            class="mt-4"
          >
            TLS <strong>Full (Strict)</strong> requires a valid certificate on all origin servers.  
            Changes apply instantly with zero downtime.
          </VAlert>
        </VCardText>
      </VCard>
    </VCol>


    <VCol cols="6">
      <VCard
        title="Protection Status"
        subtitle="Control SentriGate security & performance layers"
      >
        <VCardText>
          <VList class="card-list">
            <VListItem
              v-for="feature in protectionFeatures"
              :key="feature.key"
              :subtitle="feature.text"
            >
              <template #prepend>
                <VAvatar
                  variant="tonal"
                  color="primary"
                  size="36"
                  class="me-2"
                >
                  <VIcon :icon="feature.icon" />
                </VAvatar>
              </template>

              <template #title>
                <h6 class="text-h6">{{ feature.title }}</h6>
              </template>

              <template #append>
                <VSwitch
                  v-model="feature.enabled"
                  density="compact"
                  color="primary"
                />
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="6">
      <VCard
        title="Agents & Integrations"
        subtitle="Download SentriGate agents for your framework"
      >
        <VCardText>
          <VList class="card-list">
            <VListItem
              v-for="agent in agents"
              :key="agent.key"
              :subtitle="agent.text"
            >
              <template #prepend>
                <VAvatar
                  variant="tonal"
                  color="secondary"
                  size="36"
                  class="me-2"
                >
                  <VIcon :icon="agent.icon" />
                </VAvatar>
              </template>

              <template #title>
                <div class="d-flex align-center gap-2">
                  <h6 class="text-h6">{{ agent.title }}</h6>
                  <VChip
                    v-if="agent.installed"
                    size="small"
                    color="success"
                    label
                  >
                    Installed
                  </VChip>
                </div>
              </template>

              <template #append>
                <VBtn
                  v-if="!agent.installed"
                  :href="agent.downloadUrl"
                  size="small"
                >
                  Download
                </VBtn>

                <VBtn
                  v-else
                  size="small"
                 
                >
                  Manage
                </VBtn>
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
   <VCard
      title="Latest Security Logs"
      subtitle="View and manage AI-generated events for SentriGate"
    >
        <VCardText>
      <LatestLogs />

      <div>
        <VBtn
                  size="small"
                >
                  Show All
                </VBtn>
      </div>
      </VCardText>
    </VCard>
    </VCol>
  </VRow>
  
</template>
