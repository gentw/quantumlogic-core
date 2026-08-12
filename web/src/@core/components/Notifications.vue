<script setup>
import { useChatStore } from '@core/stores/useChatStore';
import * as feather from 'feather-icons';
import { onMounted } from 'vue';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
const router = useRouter();
const route = useRoute();
const props = defineProps({
  showNotificationsOverlay: {
    type: [Boolean, false],
    default: false
  },
  notifications: {
    type: Array,
    required: true,
  },
  badgeProps: {
    type: Object,
    required: false,
    default: undefined,
  },
  location: {
    type: null,
    required: false,
    default: 'bottom end',
  },
})

const emit = defineEmits([
  'read',
  'unread',
  'remove',
  'click:notification',
  'update:notifications'
])

const chatStore = useChatStore();

onBeforeMount(()=> {
  // alert(1)
  subscribe()
  subscribe_sync_api_notif()
})

onMounted(() => {
  fetchNotifications();
});

const isAllMarkRead = computed(() => props.notifications.some(item => item.read === false))
const totalUnread = ref(0);
const renderFeatherIcon = (iconName, size) => {
  const icon = feather.icons[iconName];
  
  if (icon) {
    return icon.toSvg({ width: size, height: size });
  }

  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

const markAllReadOrUnread = () => {
  const allNotificationsIds = props.notifications.map(item => item.id)
  if (!isAllMarkRead.value)
    emit('unread', allNotificationsIds)
  else
    emit('read', allNotificationsIds)
}

const totalUnseenNotifications = computed(() => {
  return props.notifications.filter(item => item.read === false).length
})

const clickNotification = async (notification) => {
  try {
    const response = await $api('/v1/notifications/readUnread', {
      method: 'POST',
      body: {
        'id': notification.id,
        'type': 'read'
      },
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if(response == 'success') {
      fetchNotifications()
      toggleOverlay(false)

      if(useCookie('userData').value.role == 'agent' && notification.type == 'chat') {
        assignAgentToClient(notification.user_id, notification.chat_id)
        fetchMessagesByChatIdAndClient(notification.user_id, notification.chat_id)
        useCookie('chatClientId').value = notification.user_id
        useCookie('chatChatId').value = notification.chat_id
      }

      if(useCookie('userData').value.role == 'client' && notification.type == 'chat') {
        chatStore.openClientChat();
      }

      if(useCookie('userData').value.role == 'client' && notification.type == 'alarm') {
        if(route.name === 'client-alarm-alerts-details-id') {
          router.push('/');
          setTimeout(()=>{
            router.push({
            name: 'client-alarm-alerts-details-id',
            params: { id: notification.alarm.id },
            query: { name: notification.alarm.alarm_description }
          });
          },1000);
        } else {
          router.push({
            name: 'client-alarm-alerts-details-id',
            params: { id: notification.alarm.id },
            query: { name: notification.alarm.alarm_description }
          });
        }        
      }
    }
    
  } catch (error) {
    console.error("Error sending message:", error);
  }
}

const assignAgentToClient = async (client_id, chat_id) => {
  try {
    const response = await $api('/v1/chat/assignAgentToClient', {
      method: 'POST',
      body: {
        'client_id': client_id,
        'chat_id': chat_id
      },
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if(response.status == 'connected_with_me') {
      chatStore.openChat();
      // newMessagesCount.value = 0;
    } else {
      alert("Ky klient eshte lidhur me nje agjent tjeter aktualisht.");
    }
    
  } catch (error) {
    console.error("Error sending message:", error);
  }
}

const fetchMessagesByChatIdAndClient = async (client_id, chat_id) => {
  try {
    const response = await $api('/v1/chat/fetchMessagesByChatIdAndClient', {
      method: 'POST',
      body: {
        'client_id': client_id,
        'chat_id': chat_id,
      },
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (response.messages.length > 0) {
      chatStore.participants = []
      if(chatStore.participants.length == 0) {
        addParticipant({
          id: response.messages[0].user_id,
          name: response.messages[0].user.name,
          imageUrl: response.messages[0].user.img
        });
      }
      chatStore.messageList = []
      response.messages.forEach(message => {
        //qitu
        if(message.user_id != useCookie("userData").value.id) {
          addToMessageList({
              author: message.user.name,
              type: "text",
              data: {
                  text: message.message,
                  status: 'received'
              },
          });
        } else {
          addToMessageList({
            author: 'me',
            type: "text",
            data: { 
              text: message.message,
              status: 'sent'
            },
          });
        }
      });
    } 
    
  } catch (error) {
    console.error("Error sending message:", error);
  }
}

const addToMessageList = (message) => {
  chatStore.messageList = [...chatStore.messageList, message];
};

const addParticipant = (participant) => {
  console.log(participant);
  chatStore.participants.push({
    id: participant.id,
    name: participant.name,
    imageUrl: participant.img
  });
};

const toggleReadUnread = async (read, Id) => {
  if (read)
    // emit('unread', [Id])
    try {
      const response = await $api('/v1/notifications/readUnread', {
        method: 'POST',
        body: {
          'id': Id,
          'type': 'unread'
        },
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if(response == 'success') {
        fetchNotifications();
      }
      
    } catch (error) {
      console.error("Error sending message:", error);
    }
  else
    // emit('read', [Id])
    try {
      const response = await $api('/v1/notifications/readUnread', {
        method: 'POST',
        body: {
          'id': Id,
          'type': 'read'
        },
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if(response == 'success') {
        fetchNotifications();
      }
      
    } catch (error) {
      console.error("Error sending message:", error);
    }
}


const menuVisible = ref(false);

const toggleOverlay = (value) => {
  menuVisible.value = value;

  emit('update:showNotificationsOverlay', value);
};

const fetchNotifications = async () => {
  try {
      const response = await $api('/v1/notifications/fetch', {
        method: 'POST',
        body: {},
        headers: {
          'Content-Type': 'application/json',
        },
      });

      const { notifications, total_notifications, unread_notifications } = response;
      totalUnread.value = unread_notifications;
      emit('update:notifications', notifications);
      console.log(notifications);
      
    } catch (error) {
      console.error("Error sending message:", error);
    }
}

const subscribe = () => {  
  if(useCookie('userData').value.role == 'agent') {
    let pusher = new Pusher('58603e7879be559844d7', { cluster: 'eu' })
    const userId = ref(useCookie('userData').value.id);
    pusher.subscribe(`notification.agent.${userId.value}`)

    pusher.bind('Notification', data => {
      // alert(1);
      fetchNotifications();
    })
  }

  if(useCookie('userData').value.role == 'client') {
    let pusher = new Pusher('58603e7879be559844d7', { cluster: 'eu' })

    const userId = ref(useCookie('userData').value.id);
    pusher.subscribe(`notification.client.${userId.value}`)

    pusher.bind('Notification', data => {
      // alert(1);
      fetchNotifications();
    })
  }
}


const subscribe_sync_api_notif = () => {  
  let _pusher = new Pusher('f4b17e21601abcc09c58', { cluster: 'eu' })
  if(useCookie('userData').value.role == 'client') {
    const _userId = ref(useCookie('userData').value.id);
    _pusher.subscribe(`notification.client.${_userId.value}`)

    _pusher.bind('Notification', data => {
      // alert(1);
      fetchNotifications();
    })
  }
}
</script>

<template>
   
  <IconBtn id="notification-btn">
    <VBadge
      v-bind="props.badgeProps"
      :model-value="props.notifications.some(n => !n.read)"
      color="error"
      dot
      offset-x="0"
      offset-y="12"
    >
    <i class="alert-icon" v-html="renderFeatherIcon('bell', 24)" />
    </VBadge>

    <VMenu
      activator="parent"
      width="380px"
      :location="props.location"
      offset="12px"
      :close-on-content-click="false"
      @update:modelValue="toggleOverlay"
      v-model="menuVisible"
      class="v-menu_main"
    >
      <!-- <template v-slot:activator="{ props }">
        <IconBtn v-bind="props">
          <VBadge
            v-bind="props.badgeProps"
            :model-value="props.notifications.some(n => !n.isSeen)"
            color="error"
            dot
            offset-x="2"
            offset-y="3"
          >
            <VIcon size="24" icon="tabler-bell" />
          </VBadge>
        </IconBtn>
      </template> -->
      <VCard class="d-flex flex-column">
        <!-- 👉 Header -->
        <VCardItem class="notification-section">
          <VCardTitle class="text-h6">
            Njoftimet
          </VCardTitle>

          <template #append>
            <VChip
              v-show="props.notifications.some(n => !n.read)"
              size="small"
              color="primary"
              class="me-2"
            >
              {{ totalUnread }} New
            </VChip>
            <IconBtn
              v-show="totalUnread"
              size="34"
              @click="markAllReadOrUnread"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                :icon="!isAllMarkRead ? 'tabler-mail' : 'tabler-mail-opened' "
              />

              <VTooltip
                activator="parent"
                location="start"
              >
                {{ !isAllMarkRead ? 'Mark all as unread' : 'Mark all as read' }}
              </VTooltip>
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <!-- 👉 Notifications list -->
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          style="max-block-size: 100%;"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in props.notifications"
              :key="notification.title"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="one"
                min-height="66px"
                class="list-item-hover-class"
                @click="clickNotification(notification)"
              >
                <!-- Slot: Prepend -->
                <!-- Handles Avatar: Image, Icon, Text -->
                <div class="d-flex align-start gap-3">
                  <i v-if="notification.type == 'chat'" class="alert-icon" v-html="renderFeatherIcon('message-square', 24)" />
                  <i v-if="notification.type == 'alarm'" class="alert-icon" v-html="renderFeatherIcon('bell', 24)" />
                  <i v-else class="alert-icon" v-html="renderFeatherIcon('bell', 24)" />
                  
                  <div>
                    <p class="text-sm font-weight-medium mb-1">
                      {{ notification.message }}
                    </p>
                    <!-- <p
                      class="text-body-2 mb-2"
                      style=" letter-spacing: 0.4px !important; line-height: 18px;"
                    >
                      {{ notification.subtitle }}
                    </p> -->
                    <p
                      class="text-sm text-disabled mb-0"
                      style=" letter-spacing: 0.4px !important; line-height: 18px;"
                    >
                      {{ notification.time_ago }}
                    </p>
                  </div>
                  <VSpacer />

                  <div class="d-flex flex-column align-end">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.read ? 'primary' : '#a8aaae'"
                      :class="`${notification.read ? 'visible-in-hover' : ''}`"
                      class="mb-2"
                      @click.stop="toggleReadUnread(notification.read, notification.id)"
                    />

                    <!-- <VIcon
                      size="20"
                      icon="tabler-x"
                      class="visible-in-hover"
                      @click="$emit('remove', notification.id)"
                    /> -->
                  </div>
                </div>
              </VListItem>
            </template>

            <VListItem
              v-show="!props.notifications.length"
              class="text-center text-medium-emphasis"
              style="block-size: 56px;"
            >
              <VListItemTitle>Asnje njoftim i ri!</VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>

        <VDivider />

      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.notification-section {
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover {
    .visible-in-hover {
      display: block;
    }
  }
}

.notification-list.v-list {
  .v-list-item {
    border-radius: 0 !important;
    margin: 0 !important;
    padding-block: 0.75rem !important;
  }
}

// Badge Style Override for Notification Badge
.notification-badge {
  .v-badge__badge {
    /* stylelint-disable-next-line liberty/use-logical-spec */
    min-width: 18px;
    padding: 0;
    block-size: 18px;
  }
}

.v-menu_main.v-overlay .v-overlay__content {
  right: 0 !important;
  left: auto !important;
  height: 100% !important;
  max-height: 100% !important;
  inline-size: 30%;
  inset-block-start: 99px !important;
  inset-inline: unset 0 !important;
  max-block-size: 100% !important;
  max-inline-size: 1730px;
  min-inline-size: 37.2833px !important;
  transform-origin: right top 0 !important;

  --v-overlay-anchor-origin: bottom right !important;

  .ps--active-y {
    // max-block-size: none !important;
  }
}

@media (max-width: 768px) {
  .v-menu_main.v-overlay .v-overlay__content {
    max-width: 100% !important;
    inline-size: 100% !important;
    inset-block-start: 53px !important;
  }
}
</style>
