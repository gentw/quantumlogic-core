<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg2.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const form = ref({
  oneTimeCode: '', // Updated to only include one-time code
});

const isPasswordVisible = ref(false)
const authThemeImg = useGenerateImageVariant(bgImg, bgImg)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const route = useRoute()
const router = useRouter()
const loginError = ref(''); 
// const ability = useAbility()

const errors = ref({
  otp: undefined, // Add a field for one-time code errors
});

const otp = ref('')
const isOtpInserted = ref(false)

watch(otp, (newValue) => {
  if (newValue.length === 5 && errors.value.otp != undefined) {
    errors.value.otp = undefined;
  }
});

onMounted(() => {
  window.addEventListener('beforeunload', removeCookie);
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', removeCookie);
});

const removeCookie = () => {
  useCookie('isOtp').value = null;
}

const onFinish = () => {
  isOtpInserted.value = true
  setTimeout(() => {
    isOtpInserted.value = false
    verifyOtp()
  }, 2000)
}


const refVForm = ref()

const verifyOtp = async () => {
  try {
    const res = await $api('https://api-ds.bitemybytes.com/api/v1/verify-otp', {
      method: 'POST',
      body: {

        phone: useCookie('phoneNo').value,
        otp: otp.value,
      }
    });


    const { token, user } = res;

    // Set the message to the API message or an empty string if undefined
    

    // Set the isOtp cookie based on the presence of the message
    useCookie('isOtp').value = false;

    useCookie('phoneNo').value = null;

    // Store the access token for future API requests
    useCookie('accessToken').value = token.token;

    // Store user data for use in the application
    useCookie('userData').value = user;

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/');
    });
  } catch (err) {
    if (err.response && err.response.status === 401) {
      // alert("error");
      errors.value.otp = 'Incorrect Code. Try again!'; // Set specific error for unauthorized access
      return; // Exit the function if unauthorized
    }
    // console.error(err);
    // loginError.value = 'An error occurred while processing your request.'; // Set a generic error message
  }
};




</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
    </div>
  </RouterLink>

  <VRow no-gutters class="auth-wrapper bg-surface">
    <VCol cols="12" md="5" class="auth-card-v2 d-flex align-center justify-center">
      <VCard flat :max-width="500" class="mt-12 mt-sm-0 pa-4">
        <VCardText>
          <h4 class="text-h4 mb-1">
            Verify your identity
          </h4>
          <p class="mb-0">
            Check your email for one-time confirmation code.
          </p>
        </VCardText>

        <VCardText>
          <VForm ref="refVForm">
            <VRow>
              <!-- one-time code input -->
              <VCol cols="12">
                <h6 class="text-body-1">
                  Enter the one-time code:
                </h6>
                <VOtpInput
                  v-model="otp"
                  :disabled="isOtpInserted"
                  type="number"
                  class="pa-0"
                  @finish="onFinish"
                />
                <VAlert
                 v-if="errors.otp && otp.length > 5"
                variant="tonal"
                color="error"
              >{{ errors.otp }}</VAlert>
              </VCol>

              <VCol cols="12">
                <VBtn
                  block
                  :loading="isOtpInserted"
                  :disabled="isOtpInserted"
                  type="submit"
                >
                  Verify
                </VBtn>
              </VCol>

              <!-- resend code section -->
              <VCol cols="12" class="text-center">
                <div class="d-flex flex-column">
                  <span>Didn't receive the code?</span> 
                
                <a href="#">Try again</a>
              </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol md="7" class="d-none d-md-flex">
      <div class="position-relative bg-background w-100 me-0">
        <div class="d-flex align-center justify-center w-100 h-100" style="padding-inline: 6.25rem;">
          <VCard flat :max-width="600" class="mt-12 mt-sm-0 pa-4 text-over-bg position-relative">
            <VCardText>
              <h3 class="text-h3 mb-4 text-white">
                SentriGate - AI-driven security
              </h3>
              <h5 class="mb-0 text-white text-h5">
                Safeguard your websites against cyber threats, malicious traffic, and attacks with real-time, intelligent threat detection and automated protection - all from a single, powerful platform.
              </h5>
              <!-- Add app store image icons -->
              <div class="d-flex mt-6">
                <a href="#" target="_blank" rel="noopener noreferrer" class="me-4">
                  <img src="@images/google-play.png" alt="Get it on Google Play" height="40">
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer">
                  <img src="@images/app-store.png" alt="Download on the App Store" height="40">
                </a>
              </div>
            </VCardText>
          </VCard>
        </div>
        <img class="auth-footer-mask z-index-100" :src="authThemeImg" alt="auth-footer-mask" width="100">
        <div class="support-text">
          <span style="font-size: 16px;">Qendra e thirrjeve:</span>
          <a href="mailto:support@sentrigate.com" style="font-size: 17px; text-decoration: underline;" class="font-weight-bold ml-1">support@sentrigate.com</a>
        </div>
      </div>
    </VCol>
  </VRow>
  <div class="d-flex copyright-text align-end gap-x-3">
    <span class="font-weight-bold">©2026 SentriGate.</span>
    <span>All rights reserved</span>
  </div>>
</template>


<style lang="scss">
@use "@core/scss/template/pages/page-auth";

.v-otp-input {
  .v-otp-input__content {
    padding-inline: 0;
  }
}
</style>
