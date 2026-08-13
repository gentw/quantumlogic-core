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
const { t } = useI18n()
const loginError = ref(''); 
// const ability = useAbility()

const errors = ref({
  otp: undefined, // Add a field for one-time code errors
});

/** Digits in a login code, as issued by TwoFactorService::generateCode(). */
const OTP_LENGTH = 6

const otp = ref('')
const isOtpInserted = ref(false)

/**
 * Keep the field to digits, and clear a previous failure as soon as the code is
 * edited — the old rule only cleared at exactly five characters, so the alert
 * stayed on screen while a corrected code was being typed.
 */
watch(otp, value => {
  const digits = String(value ?? '').replace(/\D/g, '').slice(0, OTP_LENGTH)

  if (digits !== value) {
    otp.value = digits

    return
  }

  if (errors.value.otp)
    errors.value.otp = undefined

  // Submit on the last digit, the way the boxed input used to.
  if (digits.length === OTP_LENGTH)
    verifyOtp()
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

const refVForm = ref()

const verifyOtp = async () => {
  // The watcher fires on the sixth digit and the button can be pressed on top
  // of it, so both entry points share one in-flight guard.
  if (isOtpInserted.value) return

  if (otp.value.length !== OTP_LENGTH) {
    errors.value.otp = t('auth.codeIncomplete')

    return
  }

  isOtpInserted.value = true

  try {
    const res = await $api('/v1/verify-otp', {
      method: 'POST',
      body: {

        phone: useCookie('phoneNo').value,
        otp: otp.value,
      }
    });


    const { token, user } = res;

    // Set the message to the API message or an empty string if undefined
    

    // Null, not false: useCookie only emits a deletion header for null or
    // undefined, so `false` leaves the cookie sitting there.
    useCookie('isOtp').value = null;

    useCookie('phoneNo').value = null;

    // Login writes this so it can bounce a half-finished sign-in back here.
    // Left behind, it sends every later visit to /login straight to this page
    // — with phoneNo already cleared, which no code can then satisfy.
    useCookie('redirect_uri').value = null;

    // Store the access token for future API requests
    useCookie('accessToken').value = token.token;

    // Store user data for use in the application
    useCookie('userData').value = user;

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/');
    });
  } catch (err) {
    errors.value.otp = err.response?.status === 401
      ? t('auth.incorrectCode')
      : t('auth.verifyFailed');
  } finally {
    // Re-enable the form on every outcome. The old code left it disabled
    // whenever the request failed with anything other than a 401.
    isOtpInserted.value = false
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
            {{ $t('auth.verifyIdentity') }}
          </h4>
          <p class="mb-0">
            {{ $t('auth.verifySubtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="verifyOtp"
          >
            <VRow>
              <!-- one-time code input -->
              <VCol cols="12">
                <AppTextField
                  v-model="otp"
                  autofocus
                  :disabled="isOtpInserted"
                  :label="$t('auth.enterCode')"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  :maxlength="OTP_LENGTH"
                  placeholder="––––––"
                />
                <VAlert
                  v-if="errors.otp"
                  variant="tonal"
                  color="error"
                  class="mt-3"
                >
                  {{ errors.otp }}
                </VAlert>
              </VCol>

              <VCol cols="12">
                <VBtn
                  block
                  :loading="isOtpInserted"
                  :disabled="isOtpInserted"
                  type="submit"
                >
                  {{ $t('auth.verifyButton') }}
                </VBtn>
              </VCol>

              <!-- resend code section -->
              <VCol cols="12" class="text-center">
                <div class="d-flex flex-column">
                  <span>{{ $t('auth.noCode') }}</span> 
                
                <a href="#">{{ $t('auth.tryAgain') }}</a>
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
              {{ $t('auth.heroTitle') }}
            </h3>
            <h5 class="mb-0 text-white text-h5">
              {{ $t('auth.heroSubtitle') }}
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
          <span style="font-size: 16px;">{{ $t('auth.callCentre') }}</span>
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

.v-otp-input {
  .v-otp-input__content {
    padding-inline: 0;
  }
}
</style>
