<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import bgImg from '@images/bg2.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({ meta: { layout: 'blank' } })

const form = ref({
  email: '',
  password: '',
  remember: false,
})

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

const message = ref(''); 
const token = ref(''); 
const user = ref(''); 



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
  router.push({ path: '/client' });
  //router.replace(route.query.to ? String(route.query.to) : '/'+user.role)
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
            Registration Successful
          </h4>
          <p class="mb-0">
            Please continue by selecting the best package to secure your websites.
          </p>
        </VCardText>
        
        <VCardText>
          <VForm ref="refVForm"
          @submit.prevent="onSubmit">
          
            <VRow>
               <VCol
                cols="12"
                class="text-center"
              >
             
              <VBtn
                  block
                  type="submit"
                >
                  Continue
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
              Your projects, services and support in one place. Follow the work we are doing for you, raise a ticket whenever you need us, and keep track of your invoices - all from a single dashboard.
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

