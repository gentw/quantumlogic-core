<script setup>
const { t } = useI18n()
import avatar1 from '@images/avatars/avatar-1.png';
import * as feather from 'feather-icons';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
const props = defineProps({
  showAlert: {
    type: Boolean,
    required: true,
  }

});
const refVForm = ref()
const $toast = useToast();
const data = ref({
  recipient: [],
  subject: '',
  type: 'pagese',
  priority: 'ulet',
  deliverySchedule: 'menjehere',
  execute_time: '',
  system: false
})

// const dataLocal = ref(structuredClone(data))

const isDialogVisible = ref(props.showAlert);


watch(
  () => props.showAlert,
  (newValue) => {
    if(!dateClicked.value) {
      isDialogVisible.value = newValue;
    }
  }
);

watch(
  () => isDialogVisible.value,
  (newValue) => {
    if(!dateClicked.value)
    {
      emit('update:showAlert', newValue);
    }    
  }
);


const dateClicked = ref(false);

const emit = defineEmits(['update:showAlert']);

const onSubmit = () => {  
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      data.value.recipient = selectedClients.value
      storeNotificationReminder()
  })
}

const handleDateClick = () => {
  isDialogVisible.value = true;
  dateClicked.value = true;

  setTimeout(()=> {
    dateClicked.value = false;
  },1000)
}

const renderFeatherIcon = (iconName) => {

if (feather.icons[iconName]) {
  return feather.icons[iconName].toSvg();
}
// Return default or fallback icon if necessary
return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

const selectedClients = ref([
])

const clients = ref([
  
])

const notificationTypes = [
  { value: 'pagese', title: 'Pagesë' },
  { value: 'tikete', title: 'Tiketë' },
  { value: 'sherbim', title: 'Shërbim' }
]

const priorities = [
  { value: 'ulet', title: 'Ulët' },
  { value: 'mesem', title: 'Mesëm' },
  { value: 'larte', title: 'Lartë' },
]

const deliverySchedules = [
  { value: 'menjehere', title: '⚡ Menjëherë' },
  { value: 'me_vone', title: '🕒 Më vonë' },
]
const searchText = ref(null)


const fetchUsers = async (e) => {
  // searchText.value = e.target.value;

  // / api/v1/admin/notifReminders/findUserByName
  try {
    const res = await $api('/v1/admin/notifReminders/findUserByName', {
      method: 'POST',
      body: { user: e.target.value },
      onResponseError({ response }) {
        // alert(1)
        // console.log("TEST", response);
        ///user/profile/{user}/showUserRequestData        
      },
    })

    clients.value = res.slice(0, 10).map((item) => ({
      id: item.id,
      name: item.name,
      avatar: avatar1,
    }));

    // console.log('Peoples', people)
   

  } catch (err) {
   console.log("error");
  }
  console.log(searchText.value)
}

const storeNotificationReminder = async () => {
  const res = await $api('/v1/admin/notifReminders/create', {
      method: 'POST',
      body: data.value,
      onResponseError({ response }) {
        // alert(1)
        console.log("ERROR", response);
        // console.log("TEST", response);
        console.log('error',response._data.message);
        if(response.status == "422") {
          $toast.error(response._data.message);
        }
       
        
      },
    });
    // // console.log("TEST", res.name);
    // fetchUserData();
    if(res.status == "success") {
      $toast.success('Ky reminder u ruajt me sukses!');
      isDialogVisible.value = false
    }
    
}


</script>

<template>
  <VDialog
  persistent
    v-model="isDialogVisible"
    max-width="600"
    class="notificationDialog"
  >  
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

    <!-- Dialog Content -->
    <VCard :title="$t('notifications.newTitle')">
      <VCardText>
       
          <VForm class="mt-3"
            ref="refVForm"
            @submit.prevent="onSubmit">
              <VRow>
                <VCol
                  cols="12"
                >
                <VSwitch
                  v-model="data.system"
                  :label="t('notifications.general')"
                />
              </VCol>
                
                <VCol
                  cols="12"
                  v-if="!data.system"
                >
                 
                  <AppAutocomplete
                    v-model="selectedClients"
                    chips
                    closable-chips
                    multiple
                    :items="clients"
                    item-title="name"
                    item-value="id"
                    :placeholder="$t('notifications.recipientPlaceholder')"
                    :label="$t('notifications.recipient')"
                    @keyup="fetchUsers"
                  >
                    <template #prepend-inner>
                      <i class="mt-1" v-html="renderFeatherIcon('search')" />
                    </template>

                    <template #chip="{ props, item }">
                      <VChip
                        v-bind="props"
                        :prepend-avatar="item.raw.avatar"
                        :text="item.raw.name"
                      />
                    </template>

                    <template #item="{ props, item }">
                      <VListItem
                        v-bind="props"
                        :prepend-avatar="item?.raw?.avatar"
                        :title="item?.raw?.name"
                      />
                    </template>
                  </AppAutocomplete>
                </VCol>

                <VCol
                  cols="12"
                  md="12"
                >
                  <AppTextField
                  
                    v-model="data.subject"
                    :label="$t('notifications.subject')"
                    :placeholder="$t('notifications.subjectPlaceholder')"
                  />
                </VCol>

                <VCol
                  cols="6"
                >
                  <AppSelect
                    :items="notificationTypes"
                    v-model="data.type"
                    :label="$t('notifications.type')"
                    :placeholder="$t('notifications.typePlaceholder')"
                  />
                </VCol>

                <VCol
                  cols="6"
                >
                  <AppSelect
                    :items="priorities"
                    v-model="data.priority"
                    :label="$t('notifications.priority')"
                    :placeholder="$t('notifications.priorityPlaceholder')"
                  />
                </VCol>

                <VCol
                  cols="12"
                >
                  <AppSelect
                    :items="deliverySchedules"
                    v-model="data.deliverySchedule"
                    :label="$t('notifications.delivery')"
                    :placeholder="$t('notifications.sendTime')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  v-if="data.deliverySchedule == 'me_vone'"
                >
                  <AppDateTimePicker
                    :label="$t('notifications.time')"
                    v-model="data.execute_time"
                    :placeholder="$t('notifications.timePlaceholder')"
                    :config="{ enableTime: true, dateFormat: 'Y-m-d H:i' }"
                    @click="handleDateClick"
                  />
                </VCol>


                


                <!-- <VCol
                  md="6"
                  cols="12"
                >
                  <AppTextField
                    v-model="accountDataLocal.surname"
                    label="Mbiemri"
                    :disabled="updateRequest"
                    placeholder="Vendosni mbiemrin"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="12"
                >
                  <AppTextField
                    v-model="accountDataLocal.email"
                    label="Email adresa juaj"
                    placeholder="email@mail.com"
                    type="email"
                    :disabled="updateRequest"
                  />
                </VCol>

              
                <VCol
                  cols="12"
                  md="12"
                >
                  <AppTextField
                    v-model="accountDataLocal.phone"
                    label="Numri telefonit"
                    placeholder="044123456"
                    :disabled="updateRequest"
                  />
                </VCol> -->

  <!--             
                <VCol
                  cols="12"
                  class="d-flex flex-wrap gap-4"
                >
                  <VBtn v-if="updateRequest == 0" type="submit">Dergo Kërkesen</VBtn>
                  <VBtn v-else color="dark-btn">
                    Kerkese per editim
                  </VBtn>

              
                </VCol> -->

                
              </VRow>
              <VCardText class="d-flex justify-end flex-wrap gap-3">
              <VBtn
                variant="tonal"
                color="secondary"
                @click="isDialogVisible = false"
              >
                {{ $t('common.close') }}
              </VBtn>
              <VBtn type="submit">
                {{ $t('notifications.create') }}
              </VBtn>
            </VCardText>
            </VForm>
      </VCardText>

      
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.notificationDialog .v-input--horizontal .v-input__prepend {
  margin-inline-end: 0 !important;
}

.notificationDialog input {
  background-color: #f9fafb !important;
}
</style>
