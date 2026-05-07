<script setup>
import avatar1 from '@images/avatars/avatar-1.png'

const domainData = {
  name: ''
}

const refInputEl = ref()
const isConfirmDialogOpen = ref(false)
const domainDataLocal = ref(structuredClone(domainData))
const isAccountDeactivated = ref(false)
const validateAccountDeactivation = [v => !!v || 'Please confirm account deactivation']

const resetForm = () => {
  domainData.value = structuredClone(domainData)
}



</script>

<template>
    <VRow>
      <VCol class="admin-client-id" cols="12">
        <VCard>
          <VCardText>
            <h4 class="text-h4 text-medium-emphasis mb-4 text-normal">
              Boost your site's speed and security
            </h4>
            <p class="text-normal">
              Connect your domain to start sending web traffic through SentriGate.
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  <VRow>
    <VCol cols="12">
      <VCard>
       

        <VCardText class="pt-2">
          <!-- 👉 Form -->
          <VForm class="mt-3">
            <VRow>
              <!-- 👉 Domain -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="domainData.name"
                  placeholder="www.example.com"
                  label="Enter an existing domain"
                />
              </VCol>

            </VRow>

            



            <VRow>

              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn>Continue</VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                  @click.prevent="resetForm"
                >
                  Cancel
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    
  </VRow>

</template>
