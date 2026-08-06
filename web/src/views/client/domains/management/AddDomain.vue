<script setup>
const router = useRouter()

const domainData = ref({
  name: '',
})

const refForm = ref()

const domainRules = [
  v => !!v || 'Please enter a domain',
  v => /^([a-z0-9-]+\.)+[a-z]{2,}$/i.test((v || '').trim()) || 'Enter a valid domain (e.g. www.example.com)',
]

const goToList = () => {
  router.push({ name: 'client-domains' })
}

const onContinue = async () => {
  const result = await refForm.value?.validate()
  if (result && result.valid === false) return

  router.push({
    name: 'client-domains-management-tab',
    params: { tab: 'verify-domain' },
    query: { domain: domainData.value.name.trim() },
  })
}

const resetForm = () => {
  domainData.value.name = ''
  refForm.value?.resetValidation()
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
            Connect your domain to start sending web traffic through QuantumLogic.
          </p>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText class="pt-2">
          <VForm
            ref="refForm"
            class="mt-3"
            @submit.prevent="onContinue"
          >
            <VRow>
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="domainData.name"
                  placeholder="www.example.com"
                  label="Enter an existing domain"
                  :rules="domainRules"
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn type="submit">
                  Continue
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  @click.prevent="resetForm"
                >
                  Reset
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="text"
                  @click.prevent="goToList"
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
