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


const desserts = [
  {
    dessert: 'Frozen Yogurt',
    calories: 159,
    fat: 6,
    carbs: 24,
    protein: 4,
  },
  {
    dessert: 'Ice cream sandwich',
    calories: 237,
    fat: 6,
    carbs: 24,
    protein: 4,
  },
  {
    dessert: 'Eclair',
    calories: 262,
    fat: 6,
    carbs: 24,
    protein: 4,
  },
  {
    dessert: 'Cupcake',
    calories: 305,
    fat: 6,
    carbs: 24,
    protein: 4,
  },
  {
    dessert: 'Gingerbread',
    calories: 356,
    fat: 6,
    carbs: 24,
    protein: 4,
  },
]


const isVerified = ref(false);
</script>

<template>
  <VRow>
    <VCol class="admin-client-id" cols="12">
      <VCard>
        <VCardText>
          <h4 class="text-h4 text-medium-emphasis mb-4 text-normal">
            Verify your domain
          </h4>

          <VAlert
            prominent
            type="info"
          >
            <template #text>
              You'll need to edit the DNS settings of your domain name.
              We have guides for popular DNS providers.
              <br />
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
                  <VChip v-if="!isVerified" color="error">
                    <VIcon start icon="tabler-alert-circle" />
                    Not Verified
                  </VChip>

                  <VChip v-else color="success">
                    <VIcon start icon="tabler-check" />
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
      <VBtn>
        Verify
      </VBtn>

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
</template>
