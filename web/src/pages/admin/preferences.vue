<script setup>
import * as feather from 'feather-icons';
/*
 "email_news_and_updates": 1,
    "email_tips_and_tutorials": 1,
    "email_my_tickets": 1,
    "email_invoices": 1,
    "email_reminders": 1,
    "push_my_tickets": 1,
    "push_my_comments": 1,
    "push_reminders": 1,
    "push_invoices": 1
    */
const notificationsEmail = ref([
  {
    logo: 'arrow-right',
    name: 'preferences.items.newsUpdates.name',
    subtitle: 'preferences.items.newsUpdates.subtitle',
    type: 'email',
    field_name: 'email_news_and_updates',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.tips.name',
    subtitle: 'preferences.items.tips.subtitle',
    type: 'email',
    field_name: 'email_tips_and_tutorials',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.tickets.name',
    subtitle: 'preferences.items.tickets.subtitle',
    type: 'email',
    field_name: 'email_my_tickets',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.invoices.name',
    color: 'yellow',
    subtitle: 'preferences.items.invoices.subtitle',
    type: 'email',
    field_name: 'email_invoices',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.reminders.name',
    subtitle: 'preferences.items.reminders.subtitle',
    type: 'email',
    field_name: 'email_reminders',
    connected: false,
  },

  {
    logo: 'asana',
    name: 'preferences.items.tickets.name',
    subtitle: 'preferences.items.tickets.subtitle',
    type: 'push',
    field_name: 'push_my_tickets',
    connected: false,
  },

  {
    logo: 'asana',
    name: 'preferences.items.comments.name',
    subtitle: 'preferences.items.comments.subtitle',
    type: 'push',
    field_name: 'push_my_comments',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.reminders.name',
    subtitle: 'preferences.items.reminders.subtitle',
    type: 'push',
    field_name: 'push_reminders',
    connected: false,
  },
  {
    logo: 'asana',
    name: 'preferences.items.invoices.name',
    subtitle: 'preferences.items.invoices.subtitle',
    type: 'push',
    field_name: 'push_invoices',
    connected: false,
  },
]);

const fetchUserPreferences = async () => {
  try {
    const res = await $api('/v1/user/preferences/fetch', {
      method: 'GET',
      onResponseError({ response }) {
        
      },
    })

    console.log(res);
  
    for (let i = 0; i < notificationsEmail.value.length; i++) {
      const fieldName = notificationsEmail.value[i].field_name;
      if (res.hasOwnProperty(fieldName)) {
        let value = res[fieldName] ? true : false;
        notificationsEmail.value[i].connected = value;
        console.log('gent',value)
      }
    }

  } catch (err) {
   console.log("error");
  }
}

const onSwitchChange = async (item) => {
  // Handle the change event here
  console.log('Switch changed for item:', item);
  console.log('New value:', item.connected);

  const jsonObject = notificationsEmail.value.reduce((acc, item) => {
    acc[item.field_name] = item.connected;
    return acc;
  }, {});

  try {
    const res = await $api('/v1/user/preferences/store', {
      method: 'POST',
      body: jsonObject,
      onResponseError({ response }) {
        
      },
    })

    const { status, data } = res
    
    if(status.value == 'success') {
      fetchUserPreferences();
    } 

  } catch (err) {
   console.log("error");
  }

  console.log(jsonObject)

}

const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

onMounted( async() => {
  await fetchUserPreferences();
});

</script>

<template>
  <VRow>
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            {{ $t('preferences.emailTitle') }}
          </h5>
          <p class="text-normal">
            {{ $t('preferences.emailSubtitle') }}
          </p>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="8">
      <VCard
        >
          <VCardText>
            <VList class="card-list" v-if="notificationsEmail.length">
              <VListItem
                v-for="item in notificationsEmail.filter(i => i.type === 'email')"
                :key="item.logo"           
              >
                <template #prepend>
                  <VAvatar start>
                    <i class="alert-icon" v-html="renderFeatherIcon('battery', 20)" />
                  </VAvatar>
                </template>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{ $t(item.name) }}
                  </h6>
                </VListItemTitle>
                <VListItemSubtitle class="text-xs">
                  {{ $t(item.subtitle) }}
                </VListItemSubtitle>
                <template #append>
                  <VListItemAction>
                    <VSwitch
                      v-model="item.connected"
                      density="compact"
                      class="me-1"
                      @change="onSwitchChange(item)"
                    />
                  </VListItemAction>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
    </VCol>
  </VRow>

  <VRow class="passwordChange">
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            {{ $t('preferences.pushTitle') }}
          </h5>
          <p class="text-normal">
            {{ $t('preferences.pushSubtitle') }}
          </p>
        </VCardText>
      </VCard>
    </VCol>
    
    <VCol cols="12" md="8">
      <VCard
        >
          <VCardText>
            <VList class="card-list">
              <VListItem
                v-for="item in notificationsEmail.filter(i => i.type === 'push')"
                :key="item.logo"
              >
                <template #prepend>
                  <VAvatar class="box" start>
                    <i class="alert-icon" v-html="renderFeatherIcon('battery', 20)" />
                  </VAvatar>
                </template>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{ $t(item.name) }}
                  </h6>
                </VListItemTitle>
                <VListItemSubtitle class="text-xs">
                  {{ $t(item.subtitle) }}
                </VListItemSubtitle>
                <template #append>
                  <VListItemAction>
                    <VSwitch
                      v-model="item.connected"
                      density="compact"
                      class="me-1"
                      @change="onSwitchChange(item)"
                    />
                  </VListItemAction>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
    </VCol>
  </VRow>
</template>

<style type="scss">
.passwordChange {
  border-block-start: 1px solid #eee;
  padding-block-start: 20px;
}

.box {
  border: 1px solid #eee !important;
  border-radius: 3px;
  background: #f6f6f6;
  block-size: 40px;
  box-shadow: none;
  inline-size: 40px;
}

.layout-wrapper.layout-nav-type-vertical .layout-content-wrapper {
  background: #fff;
}

</style>
