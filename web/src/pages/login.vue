<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const form = ref({
  phone_number: '',
  password: '',
  remember: false,
});

watch(() => form.value.phone_number, (newValue) => {
  if (newValue) {
    errors.value.phone_number = undefined;
  }
});

watch(() => form.value.password, (newValue) => {
  if (newValue) {
    errors.value.phone_number = undefined;
  }
});

const isFormDone = ref(false)
const isPasswordVisible = ref(false)
const authThemeImg = useGenerateImageVariant(bgImg, bgImg)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const route = useRoute()
const router = useRouter()
// const ability = useAbility()

const errors = ref({
  phone_number: undefined,
  password: undefined,
})

const refVForm = ref()

const redirect = ref('/'); 
const token = ref(''); 
const user = ref(''); 

onMounted( async() => {
  if(useCookie('redirect_uri').value != null) {
    await nextTick(() => {
    router.replace(route.query.to ? String(route.query.to) : useCookie('redirect_uri').value)  
    })
  }  
});



const login = async () => {
  try {
    const res = await $api('/v1/login', {
      method: 'POST',
      body: {
        phone: form.value.phone_number,
        password: form.value.password,
      },
      onResponseError({ response }) {
        errors.value.phone_number = 'Incorrect email or password!';
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
      router.replace(route.query.to ? String(route.query.to) : '/'+user.role)      
    })
    // useCookie('userAbilityRules').value = userAbilityRules
    } else {
      useCookie('phoneNo').value = form.value.phone_number
      useCookie('isOtp').value = !!redirect.value
      useCookie('redirect_uri').value = redirect.value;

      await nextTick(() => {
        router.replace(route.query.to ? String(route.query.to) : redirect.value)      
      })
      // alert(redirect.value);
    }
    isFormDone.value = false;
    

    // ability.update(userAbilityRules)

   

  } catch (err) {
    if (err.response && err.response.status === 401) {
      // alert("error");
      errors.value.phone_number = 'Incorrect email or password!!'; // Set specific error for unauthorized access
      isFormDone.value = false;
      return; // Exit the function if unauthorized
    }
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
            {{ $t('auth.signIn') }}
          </h4>
          <p class="mb-0">
            {{ $t('auth.signInSubtitle') }}
          </p>
        </VCardText>
        
        <VCardText>
          <VForm ref="refVForm"
          @submit.prevent="onSubmit">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.phone_number"
                  autofocus
                  :label="$t('auth.emailLabel')"
                  type="text"
                  :placeholder="$t('auth.emailPlaceholder')"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  :label="$t('auth.password')"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between mt-2 mb-4">
                  <VCheckbox
                    v-model="form.remember"
                    :label="$t('auth.rememberMe')"
                  />
                 
                      <router-link class="text-primary ms-2 mb-1" :to="{ name: 'reset-forgot-password'}">{{ $t('auth.forgotPassword') }}</router-link>
                </div>
                <VCol cols="12">
                <VAlert
                 v-if="errors.phone_number"
                variant="tonal"
                color="error"
              >{{ errors.phone_number }}</VAlert>
              </VCol>

                <VBtn
                  :loading="isFormDone"
                  :disabled="isFormDone"
                  block
                  type="submit"
                >
                  {{ $t('auth.loginButton') }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center"
              >
                <span>{{ $t('auth.noAccount') }}</span>

              
                <RouterLink class="text-primary ms-2" to="/register">
                  {{ $t('auth.register') }}
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
  <div class="d-flex copyright-text  text-center gap-x-2">
      <span class="font-weight-bold">{{ $t('auth.copyright', { year: new Date().getFullYear() }) }}</span>    
      <span>{{ $t('auth.rightsReserved') }}</span>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";

$theme-background: 0, 0, 0; // Default value
$theme-surface: 255, 255, 255; // Default value

:root {
  --v-theme-background: #{$theme-background} !important;
  --v-theme-surface: #{$theme-surface} !important;
}
</style>

