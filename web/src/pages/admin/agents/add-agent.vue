<script setup>
  
import useAgentList from '@/views/admin/users/agent/useAgentList';
import avatar1 from '@images/avatars/avatar-1.png';
import * as feather from 'feather-icons';
import { onMounted } from 'vue';
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

const route = useRoute('admin-clients-id')
const router = useRouter()
const clientId = route.params.id

onMounted(() => {
  
  
})

const sendMail = ref(false);
const currentPassword = ref('')
const newPassword = ref('')

const accountData = {
  avatar: avatar1,
  name: '',
  surname: '',
  password: newPassword.value,
  email: '',
  phone: '',
  address: '',
  city: '',
  postal_code: '',
  department: '',
  send_mail: sendMail.value
}

const refVForm = ref()

const updateRequest = ref(0)

const refInputEl = ref()
const isConfirmDialogOpen = ref(false)
const accountDataLocal = ref(structuredClone(accountData))

const isCurrentPasswordVisible = ref(false)
const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)

const confirmPassword = ref('')
const $toast = useToast();
const killAllSessions = ref(false)

const blockUser = ref(false);
const deactivateUser = ref(false);

const passwordInput = ref(null)

const resetForm = () => {
  accountDataLocal.value = structuredClone(accountData)
}

const refForm = ref()
const errors = ref({
  password: undefined,
})

const confirmPasswordRules = [
v => v === newPassword.value || 'Fjalëkalimet duhen te jene te njejta!', // Check if the passwords match
  v => !!v || 'Konfirmimi i passwordit eshte obligativ.',
];

const {
  registerAgentFromAdmin,
  fetchAgents,
  agentRegistered
} = useAgentList()

const renderFeatherIcon = (iconName) => {
  if (feather.icons[iconName]) {
    return feather.icons[iconName].toSvg();
  }
  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

const departments = [
  { value: 'agjent', title: 'Agjent' },
  { value: 'teknik', title: 'Teknik' },
  { value: 'financat', title: 'Financat (inkasant)' },
  { value: 'patrollat', title: 'Patrollat' },
]


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

    const res = await $api('https://api.quantumlogic.at/api/v1/user/profile/updateUserRequest', {
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
    const res = await $api('https://api.quantumlogic.at/api/v1/user/profile/updatePassword', {
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
      accountDataLocal.value.password = newPassword.value
      registerAgentFromAdmin(accountDataLocal.value).then(()=>{
        if(agentRegistered.value) {
          router.push('/admin/agents')
          fetchAgents()
          agentRegistered.value = false
          
          $toast.success('Regjistrimi u krye me sukses!');
        }        
      });
      // alert(agentRegistered.value)
      // if(agentRegistered.value) {
      //   fetchAgents()
      //   router.push('/admin/agents')
      //   agentRegistered.value = false
        
      //   $toast.success('Regjistrimi u krye me sukses!');
      // }
  })
}


// Validation rules
const rules = {
  required: (value) => !!value || 'Password is required',
  min: (value) => (value.length >= 8) || 'Password must be at least 8 characters'
};

// Computed property to check if the password is strong
const isPasswordStrong = computed(() => {
  const regex = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
  return regex.test(newPassword.value); // Password strength check
});

// Dynamically set the class based on password strength
const passwordStrengthClass = computed(() => {
  return isPasswordStrong.value ? 'input-green' : 'input-default';
});

const generateStrongPassword = () => {
  const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+[]{}|;:,.<>?";
  let password = "";
  for (let i = 0; i < 15; i++) { // You can adjust the length of the password
    password += charset.charAt(Math.floor(Math.random() * charset.length));
  }
  newPassword.value = password;
  
  // Set focus to the password input field
  // passwordInput.value?.focus();

  console.log(passwordInput.value?.inputField.focus())
};


onMounted( async() => {
 
});  
</script>

<template>
  <div class="add-client">
    <VRow>
      <VCol class="admin-client-id" cols="12">
        <VCard>
          <VCardText>
            <h4 class="text-h4 text-medium-emphasis mb-4 text-normal">
              Shtoni agjent të ri
            </h4>
            <p class="text-normal">
              Krijo një përdorues krejt të ri dhe shtoje në këtë faqe.
            </p>
          </VCardText>
        </VCard>
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
                    placeholder="Vendosni emrin"
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
                    placeholder="Vendosni mbiemrin"
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
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="12"
                >
                  <AppSelect
                    :items="departments"
                    label="Departamenti"
                    placeholder="Zgjedh departamentin"
                    v-model="accountDataLocal.department"
                  />
                </VCol>

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
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <h5 class="text-h5 text-medium-emphasis mb-4 text-normal">
              Adresa
            </h5>
            <p class="text-normal">
              Vendosni kordinatat e sakte te lokacionit
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="8">
        <VCard>
          <VCardText class="pt-2">
            <!-- 👉 Form -->
            <VForm class="mt-3"
            ref="refVForm"
            @submit.prevent="onSubmit">
              <VRow>
                <!-- 👉 Address -->
                <VCol
                  cols="12"
                  md="12"
                >
                  <AppTextField
                    v-model="accountDataLocal.address"
                    label="Adresa"
                    :disabled="updateRequest"
                    placeholder="Vendosni adresen e klientit"
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
                    placeholder="Vendosni qytetin"
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
                <!-- <VCol
                  cols="12"
                  class="d-flex flex-wrap gap-4"
                >
                  <VBtn v-if="updateRequest == 0" type="submit">Dergo Kërkesen</VBtn>
                  <VBtn v-else color="dark-btn">
                    Kerkese per editim
                  </VBtn>

              
                </VCol> -->

                
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
              Cilësimet
            </h5>
            <p class="text-normal">
              Vendosni fjalekalimin the opsionet e njoftimit
            </p>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" md="8">
        <VCard>
          <VForm ref="refForm"
            @submit.prevent="onSubmit"
          >
            <VCardText class="pt-0">
              <!-- 👉 Current Password -->
              
              <!-- 👉 New Password -->
              <VRow>
                <VCol
                  cols="12"
                  md="12"
                >
                <VBtn
                  variant="outlined"
                  color="secondary"
                  class="mb-4 w-100 generate-psw"
                  @click="generateStrongPassword"
                >
                  <div class="custom-btn-content">
                    <span>
                      Gjenero fjalëkalimin
                    </span>
                    <span style="
                            position: absolute;inset-inline-end: 15px;
">
                      <i v-html="renderFeatherIcon('lock')" />
                    </span>
                  </div>
                </VBtn>
                  <!-- 👉 new password -->
                  <AppTextField
                    ref="passwordInput"
                    v-model="newPassword"
                    :type="'text'"
                    label="Fjalëkalimi i ri"
                    autocomplete="on"
                    placeholder="············"
                    :class="'new_password'"
                    :rules="[rules.required, rules.min]"
                  >
                    <template #append-inner>
                      <span v-if="newPassword.length >= 8" class="password-strength-text">
                        Strong
                      </span>
                    </template>
                  </AppTextField>

                  <div>
                    <VCheckbox
                      class='mt-4'
                      v-model="accountDataLocal.sendMail"
                      :label="'Dërgojini përdoruesit e ri një email në lidhje me llogarinë e tij'"
                    />
                  </div>
                </VCol>

              

    
              </VRow>
            </VCardText>

          
            <VCardText class="d-flex flex-wrap gap-4">
              <VBtn class="w-100" type="submit">Shto agjent të ri</VBtn>
            </VCardText>
          </VForm>
        </VCard>
      </VCol>
    </VRow>
  </div>
  
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

.input-green .v-input__control {
  background-color: #e8f5e9 !important; /* Light green background for strong password */
}

.input-default .v-input__control {
  background-color: white !important; /* Default background for weak password */
}

.new_password {
  .v-field--variant-outlined.v-field--focused:not(.v-field--error, .v-field--success) {
    .v-field__outline {
      border-color: green;
      box-shadow: none !important;
      color: #4ade80 !important;
    }
  }
}

.password-strength-text {
  color: #222;
}

.generate-psw {
  justify-content: space-between;
  border-color: #eee;
  background: #f9fafb;
  color: #374151 !important;
}

.custom-btn-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  inline-size: 100%; /* Ensures the content spans the button width */
}

.generate-psw .v-btn__content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0;  /* Remove extra padding that might cause centering */
  grid-area: unset;
  inline-size: 100%; /* Stretch the content to fill the button */
}

.add-client .v-input.v-input--density-comfortable .v-field .v-field__input {
  background: #f9fafb;
}

.add-client .v-input.v-input--density-comfortable .v-field .v-field__input::placeholder {
  /* color: #374151 !important; */
}

/* .text-default {
  font-weight: normal !important;
} */
</style>
