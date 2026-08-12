<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg2.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

definePage({ meta: { layout: 'blank' } })

const form = ref({
  name: '',
  surname: '',
  email: '',
  phone_number: '',
  password: '',
  confirmPassword: '',
  privacy_policy: false
})

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const authThemeImg = useGenerateImageVariant(bgImg, bgImg)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
const $toast = useToast();

const route = useRoute()
const router = useRouter()
// const ability = useAbility()

const errors = ref({
  phone_number: undefined,
  password: undefined,
})

const refVForm = ref()
const isFormDone = ref(false)
const message = ref(''); 
const token = ref(''); 
const user = ref(''); 
const redirect = ref('/register'); 

onMounted( async() => {
  
});

const labelWithAsterisk = (label) => {
  return `${label} <span class='text-danger'>*</span>`;
};

const login = async () => {
  try {
    
     if (!form.value.privacy_policy) {
       $toast.error("You must agree to the privacy policy. Please enable the checkbox first.");
       isFormDone.value = false;
       return;
     }

     const res = await $api('/v1/register_client2', {
       method: 'POST',
       body: {
         name: form.value.name,
         surname: form.value.surname,
         email: form.value.email,
         phone: form.value.phone_number,
         password: form.value.password,
         confirm_password: form.value.confirmPassword
       },
       onResponseError({ response }) {
         //errors.value = response._data.errors
          Object.values(response._data.errors).flat().forEach(msg => $toast.error(msg));
          isFormDone.value = false;
          
       },
    })

    const { token, user, redirect: apiMessage } = res
      
    redirect.value = apiMessage || '';

    if(!redirect.value) {
      // Store the access token for future API requests
      useCookie('accessToken').value = token.token

      // Store user data for use in the application
      useCookie('userData').value = user

      await nextTick(() => {
      router.push({ path: '/client/register-success' });
            
    })
    // useCookie('userAbilityRules').value = userAbilityRules
    } else {
      useCookie('phoneNo').value = form.value.phone_number
      useCookie('isOtp').value = !!redirect.value
      useCookie('redirect_uri').value = redirect.value;

      await nextTick(() => {
       router.push({ path: '/client/register-success' });     
      })
      // alert(redirect.value);
    }

    isFormDone.value = false;
    
  
    

    // ability.update(userAbilityRules)

    await nextTick(() => {
      //router.replace(route.query.to ? String(route.query.to) : '/register-success')
    })
  } catch (err) {
    console.error(err)
  }
}


// const login = async () => {
//   try {
//     const res = await $api('/auth/login', {
//       method: 'POST',
//       body: {
//         email: credentials.value.email,
//         password: credentials.value.password,
//       },
//       onResponseError({ response }) {
//         errors.value = response._data.errors
//       },
//     })

//     const { accessToken, userData, userAbilityRules } = res

//     useCookie('userAbilityRules').value = userAbilityRules
//     ability.update(userAbilityRules)
//     useCookie('userData').value = userData
//     useCookie('accessToken').value = accessToken
//     await nextTick(() => {
//       router.replace(route.query.to ? String(route.query.to) : '/')
//     })
//   } catch (err) {
//     console.error(err)
//   }
// }

const onSubmit = () => {
  isFormDone.value = true;
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      login()
  })
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
   <VCol
      cols="12"
      md="5"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ $t('auth.register') }}
          </h4>
         <!-- <p class="mb-0">
            Request Access with your personal details
          </p> -->
        </VCardText>
        
        <VCardText>
          <VForm ref="refVForm"
          @submit.prevent="onSubmit">
            <VRow>
              <VCol cols="6">
                <label for="name">
                  {{ $t('auth.name') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                  id="name"
                  v-model="form.name"
                  autofocus
                  type="text"
                  placeholder=""
                />
              </VCol>

              <VCol cols="6">
                <label for="surname">
                  {{ $t('auth.surname') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                  v-model="form.surname"
                  type="text"
                />
              </VCol>


              <VCol cols="12">
                <label for="email">
                  {{ $t('auth.yourEmailAddress') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                  v-model="form.email"                 
                  type="text"
                  id="email"
                  placeholder=""
                />
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <label for="phone">
                  {{ $t('auth.yourPhone') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                  v-model="form.phone_number"                  
                  id="phone"
                  type="text"
                  placeholder="044123456"
                />
              </VCol>

              <VCol cols="12">
                <label for="password">
                  {{ $t('auth.yourPassword') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                  v-model="form.password"
                  id="password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <label for="password">
                  {{ $t('auth.confirmPassword') }} <span class="text-danger">*</span>
                </label>
                <AppTextField
                    v-model="form.confirmPassword"
                    id="password"
                    placeholder="············"
                    :type="isConfirmPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                  />

                <div class="d-flex align-center flex-wrap mt-2 mb-4">
                  <VCheckbox
                    v-model="form.privacy_policy"
                    :label="$t('auth.iAgree')"
                  />
                 
                      <router-link class="text-primary ml-1 text-black" :to="{ name: ''}">{{ $t('auth.privacyPolicy') }}</router-link>
                </div>

                <VBtn
                :loading="isFormDone"
                  :disabled="isFormDone"
                  block
                  type="submit"
                >
                  Register
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center"
              >

                <span>{{ $t('auth.haveAccount') }}</span>

                
                <RouterLink class="text-primary ms-2" to="/">
                  {{ $t('auth.loginHere') }}
                </RouterLink>
              </VCol>
              
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
      
    </VCol>

    <VCol
      md="7"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
        <VCard
        flat
        :max-width="600"
        class="mt-12 mt-sm-0 pa-4 text-over-bg position-relative"
      >
          <VCardText>
            <h3 class="text-h3 mb-4 text-white">
              {{ $t('auth.heroTitle') }}
            </h3>
            <h5 class="mb-0 text-white text-h5">
              {{ $t('auth.heroSubtitle') }}
            </h5>

            <!-- Add app store image icons -->
            <div class="d-flex mt-6">
              <a
                href="#" 
                target="_blank"
                rel="noopener noreferrer"
                class="me-4"
              >
                <img
                  src="@images/google-play.png"
                  alt="Get it on Google Play"
                  height="45"
                >
              </a>
              <a
                href="#" 
                target="_blank"
                rel="noopener noreferrer"
              >
                <img
                  src="@images/app-store.png"
                  alt="Download on the App Store"
                  height="45"
                >
              </a>
            </div>
          </VCardText>
        </VCard>
        </div>

        <img
          class="auth-footer-mask z-index-100"
          :src="authThemeImg"
          alt="auth-footer-mask"
          width="100"
        >
        <div class="support-text">
            <span style="font-size: 16px;">{{ $t('auth.contact') }}</span>    
            <a href="mailto:support@quantumlogic.at" style="font-size: 17px; text-decoration: underline;" class="font-weight-bold ml-1">support@quantumlogic.at</a>
        </div>
      </div>
    </VCol>
  </VRow>
  <div class="d-flex copyright-text align-end gap-x-3">
      <span class="font-weight-bold">{{ $t('auth.copyright', { year: new Date().getFullYear() }) }}</span>    
      <span>{{ $t('auth.rightsReserved') }}</span>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";

.text-danger { color: red; }
.text-black { color: #000 !important; }
</style>

