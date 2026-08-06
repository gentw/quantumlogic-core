<script setup>
import { loadStripe } from '@/utils/stripe'

const methods = ref([])
const loading = ref(false)
const working = ref(false)
const errorMessage = ref('')

// Add-card dialog (Stripe SetupIntent + Payment Element)
const addDialog = ref(false)
const elementReady = ref(false)
let stripe = null
let elements = null

const loadMethods = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/client/payment-methods')

    methods.value = res.data
  } catch (err) {
    console.error('Failed to load payment methods:', err)
  } finally {
    loading.value = false
  }
}

const openAddDialog = async () => {
  addDialog.value = true
  elementReady.value = false
  errorMessage.value = ''

  try {
    const [stripeInstance, intent] = await Promise.all([
      loadStripe(),
      $api('/v1/client/stripe/setup-intent', { method: 'POST' }),
    ])

    if (!stripeInstance) {
      errorMessage.value = 'Saving cards is not available right now.'

      return
    }

    stripe = stripeInstance
    elements = stripe.elements({ clientSecret: intent.client_secret })

    // The dialog body must exist before mounting.
    await nextTick()
    elements.create('payment').mount('#setup-element')
    elementReady.value = true
  } catch (err) {
    console.error('Failed to start saving a card:', err)
    errorMessage.value = err.data?.message ?? 'Could not start saving the card.'
  }
}

const confirmSetup = async () => {
  if (!stripe || !elements) return
  working.value = true
  errorMessage.value = ''

  // The setup_intent.succeeded webhook stores the method server-side.
  const { error } = await stripe.confirmSetup({
    elements,
    confirmParams: { return_url: `${window.location.origin}/client/billing/payment-methods` },
  })

  if (error) {
    errorMessage.value = error.message
    working.value = false
  }
}

const removeMethod = async method => {
  working.value = true
  try {
    await $api(`/v1/client/payment-methods/${method.id}`, { method: 'DELETE' })
    await loadMethods()
  } catch (err) {
    console.error('Failed to remove the payment method:', err)
  } finally {
    working.value = false
  }
}

const makeDefault = async method => {
  working.value = true
  try {
    await $api(`/v1/client/payment-methods/${method.id}/default`, { method: 'POST' })
    await loadMethods()
  } catch (err) {
    console.error('Failed to set the default payment method:', err)
  } finally {
    working.value = false
  }
}

onMounted(loadMethods)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Saved payment methods</VCardTitle>
      <template #append>
        <VBtn prepend-icon="tabler-plus" @click="openAddDialog">
          Add card
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VProgressCircular v-if="loading" indeterminate class="d-block mx-auto my-6" />

      <template v-else-if="methods.length">
        <VList lines="two" class="border rounded">
          <VListItem v-for="method in methods" :key="method.id">
            <template #prepend>
              <VAvatar variant="tonal" color="primary" rounded>
                <VIcon :icon="method.provider === 'paypal' ? 'tabler-brand-paypal' : 'tabler-credit-card'" />
              </VAvatar>
            </template>

            <VListItemTitle class="text-capitalize">
              {{ method.brand ?? method.provider }} •••• {{ method.last4 }}
              <VChip v-if="method.is_default" size="x-small" color="primary" label class="ms-2">
                Default
              </VChip>
            </VListItemTitle>
            <VListItemSubtitle v-if="method.exp_month">
              Expires {{ String(method.exp_month).padStart(2, '0') }}/{{ method.exp_year }}
            </VListItemSubtitle>

            <template #append>
              <VBtn
                v-if="!method.is_default"
                size="small"
                variant="text"
                :disabled="working"
                @click="makeDefault(method)"
              >
                Make default
              </VBtn>
              <VBtn
                size="small"
                variant="text"
                color="error"
                icon="tabler-trash"
                :disabled="working"
                @click="removeMethod(method)"
              />
            </template>
          </VListItem>
        </VList>
      </template>

      <VAlert v-else type="info" variant="tonal">
        No saved payment methods yet. Cards saved during checkout or added
        here can be used for recurring services.
      </VAlert>
    </VCardText>
  </VCard>

  <!-- Add card dialog -->
  <VDialog v-model="addDialog" max-width="480">
    <VCard title="Add a card">
      <VCardText>
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ errorMessage }}
        </VAlert>
        <div id="setup-element" />
        <VProgressCircular v-if="!elementReady && !errorMessage" indeterminate class="d-block mx-auto my-4" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" color="secondary" @click="addDialog = false">
          Cancel
        </VBtn>
        <VBtn color="primary" :loading="working" :disabled="!elementReady" @click="confirmSetup">
          Save card
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
