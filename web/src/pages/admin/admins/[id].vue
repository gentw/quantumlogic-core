<script setup>
  
import avatar1 from '@images/avatars/avatar-1.png';
import { onMounted } from 'vue';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

const route = useRoute('admin-clients-id')
const clientId = route.params.id;

onMounted(() => {
  
  
})


const accountData = {
  avatar: avatar1,
  name: '',
  surname: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  postal_code: '',
}

const refVForm = ref()

const updateRequest = ref(0)

const refInputEl = ref()
const isConfirmDialogOpen = ref(false)
const accountDataLocal = ref(structuredClone(accountData))

const isCurrentPasswordVisible = ref(false)
const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const $toast = useToast();
const killAllSessions = ref(false)

const blockUser = ref(false);
const deactivateUser = ref(false);

const resetForm = () => {
  accountDataLocal.value = structuredClone(accountData)
}

const refResetPswForm = ref()

const errors = ref({
  password: undefined,
})

const confirmPasswordRules = [
v => v === newPassword.value || 'Fjalëkalimet duhen te jene te njejta!', // Check if the passwords match
  v => !!v || 'Konfirmimi i passwordit eshte obligativ.',
];

const changeAvatar = file => {
  const fileReader = new FileReader()
  const { files } = file.target
  if (files && files.length) {
    fileReader.readAsDataURL(files[0])
    fileReader.onload = () => {
      if (typeof fileReader.result === 'string')
        accountDataLocal.value.avatar = fileReader.result
    }
  }
}

const changeAvatar2 = file => {
  const fileReader = new FileReader()
  const { files } = file.target;
  if (files && files.length) {
    fileReader.readAsDataURL(files[0])
    fileReader.onload = () => {
      if (typeof fileReader.result === 'string')
        accountDataLocal.value.avatar_temp = fileReader.result
        accountDataLocal.value.avatar = files[0];  // Ensure this is a File object
      }
  }
}

// reset avatar image
const resetAvatar = () => {
  accountDataLocal.value.avatar = accountData.avatar
}

const fetchUserData = async () => {
  try {
    const res = await $api('https://api.quantumlogic.at/api/v1/user/profile/'+clientId+'/showUserDataById', {
      method: 'POST',
      onResponseError({ response }) {
        // alert(1)
        // console.log("TEST", response);
        
      },
    })

    console.log(res);
    accountDataLocal.value.name = res.name;
    accountDataLocal.value.surname = res.surname;
    accountDataLocal.value.email = res.email;
    accountDataLocal.value.phone = res.phone;
    accountDataLocal.value.address = res.address;
    accountDataLocal.value.city = res.city;
    accountDataLocal.value.postal_code = res.postal_code;
    blockUser.value = (res.blocked) ? true : false;
    deactivateUser.value = (res.deactivated) ? true : false;

    updateRequest.value = res.update_request;
    
    if(res.img == "/src/assets/images/avatars/avatar-1.png") {
      accountDataLocal.value.avatar_temp = avatar1;
    } else {
      accountDataLocal.value.avatar_temp = 'https://api.quantumlogic.at/' + res.img;
    }
    // console.log("TEST", res.name);

    

  } catch (err) {
   console.log("error");
  }
}

const storeUserData = async () => {
  try {
    const formData = new FormData();

    // Append other fields from accountDataLocal to formData
    for (const key in accountDataLocal.value) {
      if (key === 'avatar' && accountDataLocal.value.avatar instanceof File) {
        // Append the file directly for avatar if it exists
        formData.append('avatar', accountDataLocal.value.avatar);
      } else {
        formData.append(key, accountDataLocal.value[key]);
      }
    }

    const res = await $api('https://api.quantumlogic.at/api/v1/admin/updateAdmin/'+clientId, {
      method: 'POST',
      body: formData,
      onResponseError({ response }) {
        // alert(1)
        // console.log("TEST", response);
        console.log('error',response._data.errors.email);
        if(response._data?.errors?.hasOwnProperty('email')) {
          $toast.error(response._data.errors.email);
        } else if(response._data?.errors?.hasOwnProperty('phone')) {
          $toast.error(response._data.errors.phone);
        } else {
          $toast.error('Te gjitha fushat jane obligative!');
        }
       
        
      },
    });
    // console.log("TEST", res.name);
    fetchUserData();
    $toast.success('Admini u perditesua me sukses!');



  } catch (err) {
   console.log("error");
  }
}

const changePassword = async () => {
  try {
    const res = await $api('https://api.quantumlogic.at/api/v1/admin/updateAdminPassword/'+clientId, {
      method: 'POST',
      body: {
        password: newPassword.value,
        killAllSessions: killAllSessions.value,
        blockUser: blockUser.value,
        deactivateUser: deactivateUser.value
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
        if (Array.isArray(response._data.errors.password)) {
          response._data.errors.password.forEach(error => {
            $toast.error(error);
          });
        }

        // if (Array.isArray(response._data.errors.old_password)) {
        //   response._data.errors.old_password.forEach(error => {
        //     $toast.error(error);
        //   });
        // }        
      },
    })

    $toast.success("Ndryshimet u kryen me sukses!");

    setTimeout(()=> {
      window.location.reload();
    },1000);
  } catch (err) {
    console.error(err)
  }
}

const onSubmit = () => {  
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      storeUserData()
  })
}

const onSubmitResetPassword = () => {  
  refResetPswForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      changePassword()
  })
}



onMounted( async() => {
  fetchUserData();
});  
</script>

<template>
  <VRow>
    <VCol class="admin-client-id" cols="12">
      <VCard>
        <VCardText>
          <h3 class="text-h3 text-medium-emphasis mb-4 text-normal">
            <span v-if="accountDataLocal.name && accountDataLocal.surname">
            Profili i adminit: {{accountDataLocal.name}} {{ accountDataLocal.surname }}
            </span>
            <span v-else>
            Profili i adminit: {{accountDataLocal.name}} {{ accountDataLocal.surname }}
            </span>
          </h3>
        </VCardText>
        
      </VCard>
      <div style="position: absolute;border-block-end: 1px solid #eee;inline-size: 150%;inset-inline-start: 0;"></div>
    </VCol>
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            Detajet Personale
          </h5>
          <p class="text-normal">
            Përdorni një adresë të përhershme ku mund të pranoni email.
          </p>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="8">
      <VCard>
        <VCardText class="d-flex">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            size="100"
            class="me-6"
            :image="accountDataLocal.avatar_temp"
          />

          <!-- 👉 Upload Photo -->
          <form class="d-flex flex-column justify-center gap-4">
            <div class="d-flex flex-wrap gap-4">
              <VBtn
                color="primary"
                size="small"
                @click="refInputEl?.click()"
                :disabled="updateRequest"
              >
                <VIcon
                  icon="tabler-cloud-upload"
                  class="d-sm-none"
                />
                <span class="d-none d-sm-block">Ndrysho foton</span>
              </VBtn>

              <input
                ref="refInputEl"
                type="file"
                name="file"
                accept=".jpeg,.png,.jpg,GIF"
                hidden
                @input="changeAvatar2"
              >

              <VBtn
                type="reset"
                size="small"
                color="secondary"
                variant="tonal"
                @click="resetAvatar"
              >
                <span class="d-none d-sm-block">Reseto</span>
                <VIcon
                  icon="tabler-refresh"
                  class="d-sm-none"
                />
              </VBtn>
            </div>

            <p class="text-body-1 mb-0">
              Allowed JPG, GIF or PNG. Max size of 800K
            </p>
          </form>
        </VCardText>

        <VCardText class="pt-2">
          <!-- 👉 Form -->
          <VForm class="mt-3"
          ref="refVForm"
          @submit.prevent="onSubmit">
            <VRow>
              <!-- 👉 First Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.name"
                  label="Emri"
                  :disabled="updateRequest"
                />
              </VCol>

              <!-- 👉 Last Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.surname"
                  label="Mbiemri"
                  :disabled="updateRequest"
                />
              </VCol>

              <!-- 👉 Email -->
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

             
              <!-- 👉 Phone -->
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
              </VCol>

                        
              <!-- 👉 Address -->
              <VCol
                cols="12"
                md="12"
              >
                <AppTextField
                  v-model="accountDataLocal.address"
                  label="Adresa"
                  :disabled="updateRequest"
                />
              </VCol>

              <!-- 👉 State -->
              <VCol
                cols="12"
                md="8"
              >
                <AppTextField
                  v-model="accountDataLocal.city"
                  label="Qyteti"
                  :disabled="updateRequest"
                />
              </VCol>

              <!-- 👉 Zip Code -->
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="accountDataLocal.postal_code"
                  label="Kodi Postal"
                  placeholder="10000"
                  :disabled="updateRequest"
                />
              </VCol>


              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn class="w-100" type="submit">Ruaj</VBtn>
              
             
              </VCol>

              
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow class="passwordChange">
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            Ndrysho fjalëkalimin
          </h5>
          <p class="text-normal">
            Përdorni një adresë të përhershme ku mund të pranoni email.
          </p>
        </VCardText>
      </VCard>
    </VCol>
    
    <VCol cols="12" md="8">
      <VCard>
        <VForm ref="refResetPswForm"
          @submit.prevent="onSubmitResetPassword"
        >
          <VCardText class="pt-0">
            <!-- 👉 Current Password -->
            
            <!-- 👉 New Password -->
            <VRow>
              <VCol
                cols="12"
              >
                <!-- 👉 new password -->
                <AppTextField
                  v-model="newPassword"
                  :type="isNewPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isNewPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  label="Fjalëkalimi i ri"
                  autocomplete="on"
                  placeholder="············"
                  @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </VCol>

              <VCol
                cols="12"
              >
                <label class="v-label mb-1 text-body-2 text-wrap" style="line-height: 15px;" for="app-text-field-Fjalëkalimi i ri-bwt1b">Sesionet<!----></label>
                <!-- 👉 confirm password -->
                <VSwitch
                  v-model="killAllSessions"
                  :label="'Mbylli te gjitha sesionet e hapura'"
                />
              </VCol>


              <VCol
                cols="12"
                md="12"
                class="d-flex"
              >
                <div>
                  <VCheckbox
                    v-model="blockUser"
                    :label="'Bllokoni këtë përdorues nga sistemi'"
                  />
                </div>

                <div class="mr-4">
                  <VCheckbox
                    v-model="deactivateUser"
                    :label="'Çaktivizo këtë përdorues'"
                  />
                </div>

                
              </VCol>
            </VRow>
          </VCardText>

         
          <VCardText class="d-flex flex-wrap gap-4">
            <VBtn class="w-100" type="submit">Ruaj</VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VCol>
  </VRow>
  
</template>

<style type="scss">
.passwordChange {
  border-block-start: 1px solid #eee;
  padding-block-start: 20px;
}

.layout-wrapper.layout-nav-type-vertical .layout-content-wrapper {
  background: #fff;
}

.admin-client-id .v-card {
  overflow: unset !important;
}

/* .text-default {
  font-weight: normal !important;
} */
</style>

