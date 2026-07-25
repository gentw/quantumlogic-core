<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const form = ref({
  password: '',
  confirmPassword: '',
})

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const authThemeImg = useGenerateImageVariant(bgImg, bgImg)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)


const route = useRoute()
const router = useRouter()
// const ability = useAbility()

watch(() => form.value.password, (newValue) => {
  if (newValue) {
    errors.value.password = undefined;
  }
});

watch(() => form.value.confirmPassword, (newValue) => {
  if (newValue) {
    errors.value.password = undefined;
  }
});

const errors = ref({
  password: undefined,
})

const refVForm = ref()

const confirmPasswordRules = [
v => v === form.value.password || 'The passwords must be the same!', // Check if the passwords match
  v => !!v || 'Password confirmation is mandatory.',
];

const message = ref(''); 
const token = ref(''); 
const user = ref(''); 
const checkTokenValidity = async () => {
  try {
    const response = await $api(`https://api-ds.bitemybytes.com/api/v1/password/token/check`, {
      method: 'POST',
      body: {
        token: route.params.token, // Send the token in the request body
      },
    });
    // console.log(route.params.token);
    // Check the response for validity
    if (response.message != 'success') { // Adjust this condition based on your API response structure
      // Redirect to home if the token is not valid
      router.push('/');
    }
  } catch (error) {
    console.error('Error checking token validity:', error);
    // Optionally redirect or handle the error
    router.push('/');
  }
};

// Call the function on component mount
onMounted(() => {
  checkTokenValidity();
});

const resetPassword = async () => {
  try {
    const res = await $api('https://api-ds.bitemybytes.com/api/v1/password/reset', {
      method: 'POST',
      body: {
        token: route.params.token,
        password: form.value.confirmPassword,
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
      },
    })

    const {message} = res

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/')
    })
  } catch (err) {
    console.error(err)
  }
}



const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      resetPassword()
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
            Create new Password
          </h4>
          <p class="mb-0">
            Please enter your new password
          </p>
        </VCardText>
        
        <VCardText>
          <VForm ref="refVForm"
          @submit.prevent="onSubmit">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- Confirm Password -->
              <VCol cols="12">
                  <AppTextField
                    v-model="form.confirmPassword"
                    label="Confirm Password"
                    placeholder="············"
                    :type="isConfirmPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                    :rules="confirmPasswordRules"
                  />
              </VCol>
              
              <VCol v-if="errors.password" cols="12">
                <VAlert                  
                  variant="tonal"
                  color="error"
                >{{ errors.password[0] }}</VAlert>
              </VCol>
              <!-- password -->
              <VCol cols="12">
                
                <VBtn
                  block
                  type="submit"
                >
                Vendos
                </VBtn>
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

