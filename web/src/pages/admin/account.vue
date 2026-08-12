<script setup>
import avatar1 from '@images/avatars/avatar-1.png';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';
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
const { t } = useI18n();

const resetForm = () => {
  accountDataLocal.value = structuredClone(accountData)
}

const refResetPswForm = ref()

const errors = ref({
  password: undefined,
})

const confirmPasswordRules = [
v => v === newPassword.value || t('account.passwordsMustMatch'), // Check if the passwords match
  v => !!v || t('account.confirmRequired'),
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
    const res = await $api('/v1/user/fetchAuthUserData', {
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

    updateRequest.value = res.update_request;
    
    if(res.img == "/src/assets/images/avatars/avatar-1.png") {
      accountDataLocal.value.avatar_temp = avatar1;
    } else {
      accountDataLocal.value.avatar_temp = assetUrl(res.img);
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

    const res = await $api('/v1/user/profile/updateUserRequest', {
      method: 'POST',
      body: formData,
      onResponseError({ response }) {
        // alert(1)
        // console.log("TEST", response);
        console.log('error',response._data.errors.email);
        if(response.status == "422") {
          $toast.error('Te gjitha fushat jane obligative!');
        }
       
        
      },
    });
    // console.log("TEST", res.name);
    fetchUserData();
    $toast.success('Kerkesa juaj shkoi me sukses!');



  } catch (err) {
   console.log("error");
  }
}

const changePassword = async () => {
  try {
    const res = await $api('/v1/user/profile/updatePassword', {
      method: 'POST',
      body: {
        old_password: currentPassword.value,
        password: confirmPassword.value,
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
        if (Array.isArray(response._data.errors.password)) {
          response._data.errors.password.forEach(error => {
            $toast.error(error);
          });
        }

        if (Array.isArray(response._data.errors.old_password)) {
          response._data.errors.old_password.forEach(error => {
            $toast.error(error);
          });
        }        
      },
    })

    $toast.success("Fjalekalimi u ndryshua me sukses!");

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
  await fetchUserData();
});

</script>

<template>
  <VRow>
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            {{ $t('account.personalDetails') }}
          </h5>
          <p class="text-normal">
            {{ $t('account.emailNote') }}
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
                <span class="d-none d-sm-block">{{ $t('account.changePhoto') }}</span>
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
                <span class="d-none d-sm-block">{{ $t('common.reset') }}</span>
                <VIcon
                  icon="tabler-refresh"
                  class="d-sm-none"
                />
              </VBtn>
            </div>

            <p class="text-body-1 mb-0">
              {{ $t('account.photoHint') }}
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
                  :label="$t('auth.name')"
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
                  :label="$t('auth.surname')"
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
                  :label="$t('account.yourEmail')"
                  :placeholder="$t('account.emailPlaceholder')"
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
                  :label="$t('account.phone')"
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
                  :label="$t('account.address')"
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
                  :label="$t('account.city')"
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
                  :label="$t('account.postalCode')"
                  placeholder="10000"
                  :disabled="updateRequest"
                />
              </VCol>

              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn v-if="updateRequest == 0" type="submit">{{ $t('account.sendRequest') }}</VBtn>
                <VBtn v-else color="dark-btn">
                  {{ $t('account.editRequestPending') }}
                </VBtn>

             
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow>
    <VCol cols="12" md="6">
      <TwoFactorCard />
    </VCol>
  </VRow>

  <VRow class="passwordChange">
    <VCol cols="12" md="4">
      <VCard>
        <VCardText>
          <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
            {{ $t('account.changePassword') }}
          </h5>
          <p class="text-normal">
            {{ $t('account.emailNote') }}
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
            <VRow>
              <VCol
                cols="12"
                md="8"
              >
                <!-- 👉 current password -->
                <AppTextField
                  v-model="currentPassword"
                  :type="isCurrentPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isCurrentPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="$t('twoFactor.currentPassword')"
                  autocomplete="on"
                  placeholder="············"
                  @click:append-inner="isCurrentPasswordVisible = !isCurrentPasswordVisible"
                />
              </VCol>
            </VRow>

            <!-- 👉 New Password -->
            <VRow>
              <VCol
                cols="12"
                md="8"
              >
                <!-- 👉 new password -->
                <AppTextField
                  v-model="newPassword"
                  :type="isNewPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isNewPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="$t('account.newPassword')"
                  autocomplete="on"
                  placeholder="············"
                  @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </VCol>

              <VCol
                cols="12"
                md="8"
              >
                <!-- 👉 confirm password -->
                <AppTextField
                  v-model="confirmPassword"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="$t('account.confirmPassword')"
                  autocomplete="on"
                  placeholder="············"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                  :rules="confirmPasswordRules"
                  />
              </VCol>
            </VRow>
          </VCardText>

         
          <VCardText class="d-flex flex-wrap gap-4">
            <VBtn type="submit">{{ $t('common.save') }}</VBtn>
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

/* .text-default {
  font-weight: normal !important;
} */
</style>
