<script setup>
import avatar1 from '@images/avatars/avatar-1.png';
import avatar2 from '@images/avatars/avatar-2.png';
import * as feather from 'feather-icons';

const colors = {
  high: 'primary',
  medium: 'warning',
  low: 'success',  
  no_priority: 'secondary',
}

/*
not_started in_progress closed in_review
*/
const items = [
 
  {
    title: 'Probleme me modulin e alarmit të sigurisë së lartë',
    users: [
      {
        name: "Filan Fisteku",
        avatar: avatar1
      },

      {
        name: "Filan Fisteku2",
        avatar: avatar2
      },
    ],
    priority: "high",
    type: 'Kerkese',
    date: '15-04-2024 - 18:00',
    days: '3 dite',
    color: 'success',
    status: 'not_started'
  },

  {
    title: 'Probleme me modulin e alarmit të sigurisë së lartë',
    users: [
      {
        name: "Filan Fisteku",
        avatar: avatar1
      },

      {
        name: "Filan Fisteku2",
        avatar: avatar2
      },
    ],
    priority: "medium",
    type: 'Kerkese',
    date: '15-04-2024 - 18:00',
    days: '3 dite',
    color: 'warning',
    status: 'in_progress'
  },

  {
    title: 'Probleme me modulin e alarmit të sigurisë së lartë',
    users: [
      {
        name: "Filan Fisteku",
        avatar: avatar1
      },

      {
        name: "Filan Fisteku2",
        avatar: avatar2
      },
    ],
    priority: "low",
    type: 'Kerkese',
    date: '15-04-2024 - 18:00',
    days: '3 dite',
    color: 'secondary',
    status: 'closed'
  },

  {
    title: 'Probleme me modulin e alarmit të sigurisë së lartë',
    users: [
      {
        name: "Filan Fisteku",
        avatar: avatar1
      },

      {
        name: "Filan Fisteku2",
        avatar: avatar2
      },
    ],
    priority: "no_priority",
    type: 'Kerkese',
    date: '15-04-2024 - 18:00',
    days: '3 dite',
    color: 'info',
    status: 'in_review'
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
<div class="mt-6">
  
  <VList
    lines="three"
     v-for="(data, index) in items"
      :key="index"
    :style="`border-radius:0; border-left: 6px solid rgba(var(--v-theme-${colors[data.priority]}),1)`"
   
  >
    <RouterLink to="#" 
    class="d-flex justify-space-between mb-3">
    <div class="d-flex flex-column">
      <div class="head d-flex"  style="align-items: center;">
        <h4 class="text-h4">
            {{data.title}}
        </h4>
        <div class="ml-2">
          <VChip :color="colors[data.priority]">
            <span v-if="data.priority == 'low'">I Ultë</span>
            <span v-if="data.priority == 'high'">I Lartë</span>
            <span v-if="data.priority == 'medium'">Mesatar</span>
            <span v-if="data.priority == 'no_priority'">Pa prioritet</span>
          </VChip>
        </div>
      </div>

      <div class="details d-flex flex-row mt-4" style="align-items: center;">
        <div class="v-avatar-group latest-tickets-avatar-group">
          <VAvatar :size="40"
             v-for="(user, _index) in data.users"
          >
            <VImg :src="user.avatar" />
            <VTooltip
              activator="parent"
              location="top"
            >
              {{user.name}}
            </VTooltip>
          </VAvatar>         


          <VAvatar
            :size="40"
            :color="$vuetify.theme.current.dark ? '#373b50' : '#eeedf0'"
          >
            +3
          </VAvatar>
        </div>

        <div class="mx-4">
          <VChip
            variant="outlined"
          >
            Kerkese
          </VChip>
        </div>

        <div class="date mr-4 d-flex" style="align-items: center;">
          <i class="mr-2" v-html="renderFeatherIcon('calendar', 20)" />
          <p class="gray-color">15-04-2024 - 18:00</p>
        </div>

        <div class="days">
          <p class="red font-weight-bold">3 dite</p>
        </div>


      </div>
    </div>    
    
    <div>
      <VBtn
        height="45"
        class="pay-online"
        :color="colors[data.priority]"
        variant="tonal"
        >

        <i :style="{ fill: `rgba(var(--v-theme-${colors[data.priority]}),1)` }" class="mt-1 mr-2" v-html="renderFeatherIcon('circle', 15)" />
            <span v-if="data.status == 'not_started'">Nuk ka filluar</span>
            <span v-if="data.status == 'closed'">Përfunduar</span>
            <span v-if="data.status == 'in_review'">Në rishikim</span>
            <span v-if="data.status == 'in_progress'">Në progres</span>
          
        </VBtn>
      </div>
      </RouterLink>
  </VList>
</div>
</template>
<style lang="scss" scoped>
.latest-tickets-avatar-group {
  &.v-avatar-group {
    .v-avatar {
      &:last-child {
        border: none;
      }
    }
  }
}

p {
  margin: 0 !important;
}

.gray-color,
.feather {
  color: #aeaeae;
}

.v-list {
  padding: 25px;
}

</style>
