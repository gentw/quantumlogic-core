<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const form = ref({
  email: ''
})

const isPasswordVisible = ref(false)
const authThemeImg = useGenerateImageVariant(bgImg, bgImg)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const route = useRoute()
const router = useRouter()
// const ability = useAbility()
watch(() => form.value.email, (newValue) => {
  if (newValue) {
    errors.value.email = undefined;
  }
});

const errors = ref({
  email: undefined
})

const refVForm = ref()

const message = ref(''); 
const token = ref(''); 
const user = ref(''); 

const isFormDone = ref(false)

onMounted( async() => {
  window.addEventListener('beforeunload', removeCookie);
});

const removeCookie = () => {
  useCookie('isOtp').value = null;
  useCookie('redirect_uri').value = null;
}

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', removeCookie);
});

const registerClientEmail = async () => {
  try {
    const res = await $api('https://api-ds.bitemybytes.com/api/v1/register-client-email', {
      method: 'POST',
      body: {
        email: form.value.email,
        phone: useCookie('phoneNo').value,
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
        errors.value.email = response._data.message;
        isFormDone.value = false;
      },
    })

    const { token, user } = res
    // Store user data for use in the application
    // useCookie('userData').value = user;
    isFormDone.value = false;
    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/checkpoint');
    });

    // ability.update(userAbilityRules)

  } catch (err) {
    console.error(err);
    isFormDone.value = false;
  }
}


const onSubmit = () => {
  isFormDone.value = true;
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      registerClientEmail();
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
            Register your email
          </h4>
          <p class="mb-0">
            To keep your account secure you must register your email on this account.
          </p>
        </VCardText>
        
        <VCardText>
          <VForm ref="refVForm"
          @submit.prevent="onSubmit">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  label="Your email"
                  type="text"
                />
              </VCol>

              <VCol v-if="errors.email" cols="12">
                <VAlert                  
                  variant="tonal"
                  color="error"
                >{{ errors.email }}</VAlert>
              </VCol>

              <!-- password -->
              <VCol cols="12">
                
                <VBtn
                   :loading="isFormDone"
                  :disabled="isFormDone"
                  block
                  type="submit"
                >
                  Confirm
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center"
              >
                <span>Don't remember the email?</span>

                <a
                  class="text-primary ms-2"
                  href="/login"
                >
                Try next time
                </a>
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
              QuantumLogic - Web, software & cloud solutions
            </h3>
            <h5 class="mb-0 text-white text-h5">
              Safeguard your websites against cyber threats, malicious traffic, and attacks with real-time, intelligent threat detection and automated protection - all from a single, powerful platform.
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
            <span style="font-size: 16px;">Contact:</span>    
            <a href="mailto:support@quantumlogic.at" style="font-size: 17px; text-decoration: underline;" class="font-weight-bold ml-1">support@quantumlogic.at</a>
        </div>
      </div>
    </VCol>
  </VRow>
  <div class="d-flex copyright-text align-end gap-x-3">
      <span class="font-weight-bold">©2026 QuantumLogic.</span>    
      <span>All rights reserved</span>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";
</style>

