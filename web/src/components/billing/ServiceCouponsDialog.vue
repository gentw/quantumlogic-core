<script setup>
/**
 * Discount codes for one service. Each code is a personalised order link —
 * `/order/{slug}?coupon={code}` — so a quote can be sent to one prospect at a
 * price nobody else sees. The backend builds the link, we only display it.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  service: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits({
  'update:modelValue': null,
})

const coupons = ref([])
const loading = ref(false)
const working = ref(false)
const error = ref('')
const copied = ref(null)

const blankForm = () => ({
  code: '',
  label: '',
  discount_percent: 10,
  valid_through: '',
  max_uses: null,
})

const form = ref(blankForm())

const listNet = computed(() => Number(props.service?.default_price_net ?? 0))

/** What the buyer would actually pay net, so the admin sees the offer, not the percentage. */
const discountedNet = computed(() => {
  const percent = Number(form.value.discount_percent ?? 0)

  return listNet.value * (1 - percent / 100)
})

const load = async () => {
  if (!props.service) return

  loading.value = true
  error.value = ''
  try {
    coupons.value = (await $api(`/v1/admin/billing/services/${props.service.id}/coupons`)).data
  } catch (err) {
    error.value = err.data?.message ?? 'Could not load the discount codes.'
  } finally {
    loading.value = false
  }
}

// The dialog is kept mounted by the parent, so reload whenever it reopens.
watch(() => [props.modelValue, props.service?.id], ([open]) => {
  if (!open) return
  form.value = blankForm()
  copied.value = null
  load()
})

const create = async () => {
  working.value = true
  error.value = ''
  try {
    await $api(`/v1/admin/billing/services/${props.service.id}/coupons`, {
      method: 'POST',
      body: {
        code: form.value.code.trim(),
        label: form.value.label || null,
        discount_percent: Number(form.value.discount_percent),
        // A date picker means "valid through this day", so expire at its end
        // rather than at midnight — otherwise picking today expires instantly.
        expires_at: form.value.valid_through ? `${form.value.valid_through} 23:59:59` : null,
        max_uses: form.value.max_uses ? Number(form.value.max_uses) : null,
      },
    })
    form.value = blankForm()
    await load()
  } catch (err) {
    error.value = err.data?.message ?? 'Could not create the code.'
  } finally {
    working.value = false
  }
}

const deactivate = async coupon => {
  working.value = true
  error.value = ''
  try {
    await $api(`/v1/admin/billing/coupons/${coupon.id}/deactivate`, { method: 'POST' })
    await load()
  } catch (err) {
    error.value = err.data?.message ?? 'Could not deactivate the code.'
  } finally {
    working.value = false
  }
}

const copyLink = async coupon => {
  try {
    await navigator.clipboard.writeText(coupon.order_url)
    copied.value = coupon.id
  } catch {
    error.value = 'Copying failed — select the link and copy it manually.'
  }
}

const statusOf = coupon => {
  if (!coupon.active) return { text: 'inactive', color: 'secondary' }
  if (coupon.max_uses && coupon.used_count >= coupon.max_uses) return { text: 'used up', color: 'warning' }
  if (coupon.expires_at && new Date(coupon.expires_at) < new Date()) return { text: 'expired', color: 'warning' }

  return { text: 'live', color: 'success' }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="900"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="service">
      <VCardItem>
        <VCardTitle>Discount codes — {{ service.name }}</VCardTitle>
        <VCardSubtitle>
          Each code is a personalised order link. List price {{ formatMoney(listNet) }} net.
        </VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VAlert v-if="!service.is_publicly_orderable" type="warning" variant="tonal" density="compact" class="mb-4">
          {{ $t('coupons.notOrderableWarn1') }} <strong>Publicly orderable</strong> {{ $t('coupons.notOrderableWarn2') }}
        </VAlert>

        <VAlert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
          {{ error }}
        </VAlert>

        <!-- New code -->
        <VRow dense class="mb-2">
          <VCol cols="12" sm="4">
            <VTextField
              v-model="form.code"
              label="Code"
              placeholder="for-eros-sefa"
              persistent-hint
              :hint="$t('coupons.codeHint')"
            />
          </VCol>
          <VCol cols="12" sm="4">
            <VTextField v-model="form.label" :label="$t('coupons.label')" placeholder="Eros Sefa — referral" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField
              v-model.number="form.discount_percent"
              :label="$t('invoice.discountPercent')"
              type="number"
              min="0.01"
              max="100"
              persistent-hint
              :hint="`→ ${formatMoney(discountedNet)} net`"
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField
              v-model.number="form.max_uses"
              :label="$t('coupons.maxUses')"
              type="number"
              min="1"
              placeholder="∞"
              persistent-hint
              :hint="$t('coupons.blankUnlimited')"
            />
          </VCol>
          <VCol cols="12" sm="4">
            <VTextField
              v-model="form.valid_through"
              label="Valid through"
              type="date"
              persistent-hint
              :hint="$t('coupons.blankNeverExpires')"
            />
          </VCol>
          <VCol cols="12" sm="8" class="d-flex align-center">
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              :loading="working"
              :disabled="!form.code.trim() || !form.discount_percent"
              @click="create"
            >
              {{ $t('coupons.create') }}
            </VBtn>
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <div v-if="loading" class="text-center py-4">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <p v-else-if="!coupons.length" class="text-body-2 mb-0">
          {{ $t('coupons.empty') }}
        </p>

        <VTable v-else class="text-no-wrap">
          <thead>
            <tr>
              <th>{{ $t('coupons.code') }}</th>
              <th class="text-end">{{ $t('invoice.discount') }}</th>
              <th class="text-end">{{ $t('services.price') }}</th>
              <th class="text-end">{{ $t('coupons.uses') }}</th>
              <th>{{ $t('coupons.validThrough') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th class="text-end">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="coupon in coupons" :key="coupon.id">
              <td>
                <div class="font-weight-medium">{{ coupon.code }}</div>
                <div class="text-body-2">{{ coupon.label || '—' }}</div>
              </td>
              <td class="text-end">{{ coupon.discount_percent }}%</td>
              <td class="text-end">{{ formatMoney(listNet * (1 - coupon.discount_percent / 100)) }}</td>
              <td class="text-end">{{ coupon.used_count }}{{ coupon.max_uses ? ` / ${coupon.max_uses}` : '' }}</td>
              <td>{{ formatDate(coupon.expires_at) }}</td>
              <td>
                <VChip size="x-small" label :color="statusOf(coupon).color">
                  {{ statusOf(coupon).text }}
                </VChip>
              </td>
              <td class="text-end">
                <VBtn
                  icon
                  size="small"
                  variant="text"
                  @click="copyLink(coupon)"
                >
                  <VIcon :icon="copied === coupon.id ? 'tabler-check' : 'tabler-link'" />
                  <VTooltip activator="parent" location="top">
                    {{ copied === coupon.id ? 'Link copied' : coupon.order_url }}
                  </VTooltip>
                </VBtn>
                <VBtn
                  v-if="coupon.active"
                  icon
                  size="small"
                  variant="text"
                  color="error"
                  :disabled="working"
                  @click="deactivate(coupon)"
                >
                  <VIcon icon="tabler-circle-off" />
                  <VTooltip activator="parent" location="top">{{ $t('coupons.deactivate') }}</VTooltip>
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="text" color="secondary" @click="emit('update:modelValue', false)">{{ $t('common.close') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
