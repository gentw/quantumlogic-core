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
                  v-model="domainData.domain"
                  placeholder="www.example.com"
                  label="Domain"
                />
              </VCol>




              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn>Add Domain</VBtn>

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
