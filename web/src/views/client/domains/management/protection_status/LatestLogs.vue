<script setup>
import * as feather from 'feather-icons';

const colors = {
  high: 'primary',
  medium: 'warning',
  low: 'success',
  no_priority: 'secondary',
};

const statusColors = {
  not_started: 'secondary',
  in_progress: 'warning',
  closed: 'success',
  in_review: 'info',
};

const logs = [
  {
    title: 'Unauthorized login attempt detected',
    priority: 'high',
    type: 'Security Alert',
    date: '07-01-2026 04:12',
    days: '0 days',
    status: 'not_started',
  },
  {
    title: 'New firewall rule triggered',
    priority: 'medium',
    type: 'Protection Event',
    date: '07-01-2026 03:50',
    days: '0 days',
    status: 'in_progress',
  },
  {
    title: 'Malware scan completed successfully',
    priority: 'low',
    type: 'Scan Result',
    date: '06-01-2026 22:30',
    days: '1 day',
    status: 'closed',
  },
  {
    title: 'Suspicious IP blocked automatically',
    priority: 'no_priority',
    type: 'System Info',
    date: '06-01-2026 21:15',
    days: '1 day',
    status: 'in_review',
  },
];

const renderFeatherIcon = (iconName, size = 16) => {
  const icon = feather.icons[iconName];
  if (icon) return icon.toSvg({ width: size, height: size });
  return feather.icons['alert-circle'].toSvg({ width: size, height: size });
};
</script>

<template>
<div class="mt-6">
  <VList
      v-for="(log, idx) in logs"
      :key="idx"
      :style="`border-radius:0; border-left: 6px solid rgba(var(--v-theme-${colors[log.priority]}),1)`"
      
      class="mb-4 p-0">

   
      <!-- Wrapper with colored left border -->
      <div
        class="d-flex justify-space-between align-center log-item-wrapper p-4 ml-4"
        
      >
        <!-- Left: Log info -->
        <div class="d-flex flex-column flex-grow-1">
          <div class="d-flex align-center mb-2">
            <h5 class="text-h5 me-2">{{ log.title }}</h5>
            <VChip :color="colors[log.priority]" small>
              <span v-if="log.priority === 'high'">High</span>
              <span v-else-if="log.priority === 'medium'">Medium</span>
              <span v-else-if="log.priority === 'low'">Low</span>
              <span v-else>None</span>
            </VChip>
          </div>
          <div class="d-flex gap-4 flex-wrap gray-color">
            <div><strong>Type:</strong> {{ log.type }}</div>
            <div><strong>Date:</strong> {{ log.date }}</div>
            <div><strong>Open:</strong> {{ log.days }}</div>
            <div>
              <strong>Status:</strong>
              <VChip :color="statusColors[log.status]" small>
                {{ log.status.replace('_', ' ') }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Right: Priority button -->
        <div>
          <VBtn
            variant="tonal"
            :color="colors[log.priority]"
            height="40"
            class="d-flex align-center"
          >
            <i v-html="renderFeatherIcon('circle', 12)" class="me-1" />
            <span v-if="log.priority === 'high'">High</span>
            <span v-else-if="log.priority === 'medium'">Medium</span>
            <span v-else-if="log.priority === 'low'">Low</span>
            <span v-else>None</span>
          </VBtn>
        </div>
      </div>
  </VList>
</div>
</template>

<style scoped>
.gray-color {
  color: #aeaeae;
}

.log-item-wrapper {
  transition: all 0.2s ease;
}

.log-item-wrapper:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
</style>
