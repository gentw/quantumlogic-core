<script setup>
const proofs = ref([])
const total = ref(0)
const loading = ref(false)
const working = ref(false)

const page = ref(1)
const statusFilter = ref('pending')
const snackbar = ref({ show: false, text: '', color: 'success' })

// Reject dialog
const rejectDialog = ref(false)
const rejectReason = ref('')
const rejectTarget = ref(null)

const notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/admin/payment-proofs', {
      query: { page: page.value, status: statusFilter.value },
    })

    proofs.value = res.data
    total.value = res.total
  } catch (err) {
    console.error('Failed to load the reconciliation queue:', err)
  } finally {
    loading.value = false
  }
}

watch([page, statusFilter], load)
onMounted(load)

const accept = async proof => {
  working.value = true
  try {
    await $api(`/v1/admin/payment-proofs/${proof.id}/accept`, { method: 'POST' })
    notify(`${proof.invoice.invoice_number} confirmed as paid.`)
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Accepting failed.', 'error')
  } finally {
    working.value = false
  }
}

const openReject = proof => {
  rejectTarget.value = proof
  rejectReason.value = ''
  rejectDialog.value = true
}

const reject = async () => {
  working.value = true
  try {
    await $api(`/v1/admin/payment-proofs/${rejectTarget.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectReason.value },
    })
    rejectDialog.value = false
    notify('Proof rejected — the client will be notified.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Rejecting failed.', 'error')
  } finally {
    working.value = false
  }
}

// The file route is auth-guarded, so a plain href would drop the Bearer
// token — fetch as a blob through $api and open the object URL.
const openSlip = async proof => {
  try {
    const blob = await $api(`/v1/admin/payment-proofs/${proof.id}/file`, { responseType: 'blob' })

    window.open(URL.createObjectURL(blob), '_blank')
  } catch (err) {
    console.error('Failed to open the slip:', err)
    notify('The slip could not be opened.', 'error')
  }
}

const amountMismatch = proof =>
  proof.payment && proof.invoice && Number(proof.payment.amount) !== Number(proof.invoice.amount_due)
</script>

<template>
  <section>
    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('reconciliation.title') }}</VCardTitle>
        <VCardSubtitle>{{ $t('reconciliation.subtitle') }}</VCardSubtitle>
        <template #append>
          <VSelect
            v-model="statusFilter"
            :items="[
              { title: 'Pending', value: 'pending' },
              { title: 'Accepted', value: 'accepted' },
              { title: 'Rejected', value: 'rejected' },
              { title: 'All', value: 'all' },
            ]"
            density="compact"
            style="min-inline-size: 10rem;"
          />
        </template>
      </VCardItem>

      <VCardText v-if="loading">
        <VProgressCircular indeterminate class="d-block mx-auto my-6" />
      </VCardText>

      <VCardText v-else-if="!proofs.length">
        <VAlert type="info" variant="tonal">{{ $t('reconciliation.empty') }}</VAlert>
      </VCardText>

      <template v-else>
        <VList lines="three">
          <VListItem v-for="proof in proofs" :key="proof.id">
            <VListItemTitle class="d-flex align-center flex-wrap gap-2">
              <span class="font-weight-medium">{{ proof.invoice?.invoice_number }}</span>
              <VChip size="x-small" label :color="{ pending: 'warning', accepted: 'success', rejected: 'error' }[proof.status]">
                {{ proof.status }}
              </VChip>
              <VChip v-if="amountMismatch(proof)" size="x-small" label color="error" variant="tonal">
                claimed {{ formatMoney(proof.payment?.amount) }} vs due {{ formatMoney(proof.invoice?.amount_due) }}
              </VChip>
            </VListItemTitle>
            <VListItemSubtitle>
              {{ proof.uploader?.name }} {{ proof.uploader?.surname }} · {{ proof.original_name }}
              ({{ Math.round(proof.size_bytes / 1024) }} KB) · {{ formatDate(proof.created_at) }}
              <div v-if="proof.note" class="text-body-2 mt-1">“{{ proof.note }}”</div>
              <div v-if="proof.rejection_reason" class="text-error mt-1">Rejected: {{ proof.rejection_reason }}</div>
            </VListItemSubtitle>

            <template #append>
              <VBtn
                size="small"
                variant="tonal"
                color="secondary"
                class="me-2"
                prepend-icon="tabler-file-search"
                @click="openSlip(proof)"
              >
                {{ $t('reconciliation.slip') }}
              </VBtn>
              <template v-if="proof.status === 'pending'">
                <VBtn
                  size="small"
                  color="success"
                  class="me-2"
                  :loading="working"
                  @click="accept(proof)"
                >
                  {{ $t('reconciliation.accept') }}
                </VBtn>
                <VBtn
                  size="small"
                  color="error"
                  variant="tonal"
                  :loading="working"
                  @click="openReject(proof)"
                >
                  {{ $t('reconciliation.reject') }}
                </VBtn>
              </template>
            </template>
          </VListItem>
        </VList>

        <VCardText>
          <VPagination v-model="page" :length="Math.ceil(total / 15)" density="compact" />
        </VCardText>
      </template>
    </VCard>

    <!-- Reject dialog -->
    <VDialog v-model="rejectDialog" max-width="440">
      <VCard :title="$t('reconciliation.rejectTitle')">
        <VCardText>
          <p class="text-body-2 mb-4">
            {{ $t('reconciliation.rejectNote') }}
          </p>
          <VTextarea v-model="rejectReason" :label="$t('reconciliation.reason')" rows="2" autofocus />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="rejectDialog = false">{{ $t('common.cancel') }}</VBtn>
          <VBtn color="error" :disabled="!rejectReason" :loading="working" @click="reject">{{ $t('reconciliation.reject') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>
