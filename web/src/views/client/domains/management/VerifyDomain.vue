<script setup>
const route = useRoute('client-domains-management-tab')
const router = useRouter()

const domainName = computed(() => (route.query?.domain ? String(route.query.domain) : ''))
const domainId = computed(() => (route.query?.id ? String(route.query.id) : ''))

const isVerified = ref(false)
const isVerifying = ref(false)

const goToList = () => {
  router.push({ name: 'client-domains' })
}

const goBack = () => {
  router.push({
    name: 'client-domains-management-tab',
    params: { tab: 'add-domain' },
    query: domainName.value ? { domain: domainName.value } : {},
  })
}

const goToProtection = () => {
  router.push({
    name: 'client-domains-management-tab',
    params: { tab: 'protection-status' },
    query: {
      ...(domainName.value && { domain: domainName.value }),
      ...(domainId.value && { id: domainId.value }),
    },
  })
}

const onVerify = async () => {
  // TODO: wire to backend `domains/{id}/verify` once available.
  isVerifying.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 400))
    isVerified.value = true
  } finally {
    isVerifying.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol class="admin-client-id" cols="12">
      <VCard>
        <VCardText>
          <h4 class="text-h4 text-medium-emphasis mb-4 text-normal">
            Verify your domain
            <span
              v-if="domainName"
              class="text-body-1 ms-2 text-disabled"
            >
              ({{ domainName }})
            </span>
          </h4>

          <VAlert
            prominent
            type="info"
          >
            <template #text>
              You'll need to edit the DNS settings of your domain name.
              We have guides for popular DNS providers.
              <br>
              Note that DNS changes can take a few minutes to propagate, but in some cases may take up to an hour.
            </template>
          </VAlert>

          <p class="text-normal mt-5">
            <strong>Step 1: Point your domain to SentriGate</strong><br><br>
            Go to your DNS provider and add the following record to route traffic to SentriGate:
          </p>

          <VTable class="text-no-wrap">
            <thead>
              <tr>
                <th>Type</th>
                <th>Hostname</th>
                <th>Content / IP</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td style="width: 240px;">
                  <VChip>A</VChip>
                </td>
                <td style="width: 240px;">
                  @
                </td>
                <td>
                  192.168.1.1
                </td>
              </tr>
            </tbody>
          </VTable>

          <p class="text-normal mt-5">
            <strong>Step 2: Verify domain ownership</strong><br><br>
            After pointing your domain to SentriGate, add the following <strong>TXT</strong> record
            to confirm that you own and control this domain.
          </p>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow>
    <VCol class="admin-client-id" cols="12">
      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>Type</th>
            <th>Hostname</th>
            <th>Content</th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td style="width: 240px;">
              <div class="d-flex">
                <div>
                  <VChip>TXT</VChip>
                </div>
                <div class="status ml-2">
                  <VChip
                    v-if="!isVerified"
                    color="error"
                  >
                    <VIcon
                      start
                      icon="tabler-alert-circle"
                    />
                    Not Verified
                  </VChip>

                  <VChip
                    v-else
                    color="success"
                  >
                    <VIcon
                      start
                      icon="tabler-check"
                    />
                    Verified
                  </VChip>
                </div>
              </div>
            </td>

            <td style="width: 240px;">
              sentrigate-verification
            </td>

            <td>
              sg_xxxxx
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCol>

    <VCol
      cols="12"
      class="d-flex flex-wrap gap-4"
    >
      <VBtn
        v-if="!isVerified"
        :loading="isVerifying"
        @click="onVerify"
      >
        Verify
      </VBtn>

      <VBtn
        v-else
        color="success"
        @click="goToProtection"
      >
        Continue
        <VIcon
          end
          icon="tabler-arrow-right"
        />
      </VBtn>

      <VBtn
        color="secondary"
        variant="tonal"
        @click.prevent="goBack"
      >
        Back
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
</template>
