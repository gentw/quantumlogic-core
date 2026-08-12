<script setup>
/**
 * Two-factor setting for the signed-in user's own account. Dropped into the
 * client, agent and admin account pages — the setting is per user, not per role.
 *
 * Turning it either way asks for the current password, so someone who has
 * borrowed an open session cannot quietly switch the second factor off.
 */
const { t } = useI18n()
const toast = useToast()

const enabled = ref(false)
const deliveryEmail = ref('')
const loading = ref(true)
const saving = ref(false)

const dialogOpen = ref(false)
const currentPassword = ref('')
const passwordError = ref('')

/** What the confirmation dialog is about to do. */
const pendingValue = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/user/two-factor')

    enabled.value = res.enabled
    deliveryEmail.value = res.delivery_email ?? ''
  } catch {
    toast.error(t('twoFactor.loadFailed'))
  } finally {
    loading.value = false
  }
}

/**
 * The switch does not change the setting on its own — it opens the password
 * prompt and snaps back, so the UI never shows a state the server has not
 * accepted.
 */
const requestChange = value => {
  pendingValue.value = value
  enabled.value = !value
  currentPassword.value = ''
  passwordError.value = ''
  dialogOpen.value = true
}

const confirm = async () => {
  saving.value = true
  passwordError.value = ''

  try {
    const res = await $api('/v1/user/two-factor', {
      method: 'POST',
      body: { enabled: pendingValue.value, current_password: currentPassword.value },
    })

    enabled.value = res.enabled
    dialogOpen.value = false
    toast.success(res.enabled ? t('twoFactor.turnedOn') : t('twoFactor.turnedOff'))
  } catch (err) {
    passwordError.value = err.response?._data?.errors?.current_password?.[0]
      ?? err.response?._data?.message
      ?? t('twoFactor.saveFailed')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ t('twoFactor.title') }}</VCardTitle>
      <VCardSubtitle>{{ t('twoFactor.subtitle') }}</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VSkeletonLoader v-if="loading" type="list-item-two-line" />

      <template v-else>
        <VSwitch
          :model-value="enabled"
          color="primary"
          :label="enabled ? t('twoFactor.on') : t('twoFactor.off')"
          @update:model-value="requestChange"
        />

        <p class="text-body-2 text-medium-emphasis mb-0 mt-2">
          {{ enabled && deliveryEmail ? t('twoFactor.codesGoTo', { email: deliveryEmail }) : t('twoFactor.explainer') }}
        </p>
      </template>
    </VCardText>

    <!-- 👉 Password confirmation -->
    <VDialog v-model="dialogOpen" max-width="460">
      <VCard>
        <VCardItem>
          <VCardTitle>
            {{ pendingValue ? t('twoFactor.confirmOnTitle') : t('twoFactor.confirmOffTitle') }}
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ pendingValue ? t('twoFactor.confirmOnBody') : t('twoFactor.confirmOffBody') }}
          </p>

          <VTextField
            v-model="currentPassword"
            type="password"
            autocomplete="current-password"
            :label="t('twoFactor.currentPassword')"
            :error-messages="passwordError"
            @keyup.enter="confirm"
          />
        </VCardText>

        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" :disabled="saving" @click="dialogOpen = false">
            {{ t('common.cancel') }}
          </VBtn>
          <VBtn :loading="saving" :disabled="!currentPassword" @click="confirm">
            {{ t('common.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
