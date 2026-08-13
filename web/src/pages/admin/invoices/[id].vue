<script setup>
const route = useRoute('admin-invoices-id')
const router = useRouter()

const invoice = ref(null)
const client = ref(null)
const payments = ref([])
const activities = ref([])
const reminders = ref([])
const working = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

// Draft-only editable fields
const draftForm = ref({ reference: '', terms: '', notes: '', due_at: '' })

// Dialogs (deep-linkable from the list's row menu via ?action=)
const paymentDialog = ref(false)
const paymentForm = ref({ amount: null, note: '' })
const reminderDialog = ref(false)
const reminderForm = ref({ offset_days: 3, channel: 'email' })

const isDraft = computed(() => invoice.value?.status === 'draft')

const notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const load = async () => {
  try {
    const res = await $api(`/v1/admin/billing/invoices/${route.params.id}`)

    invoice.value = res.invoice
    client.value = res.client
    payments.value = res.payments
    activities.value = res.activities
    reminders.value = res.reminders

    draftForm.value = {
      reference: res.invoice.reference ?? '',
      terms: res.invoice.terms ?? '',
      notes: res.invoice.notes ?? '',
      due_at: res.invoice.due_at ?? '',
    }

    paymentForm.value.amount = res.invoice.amount_due
  } catch (err) {
    console.error('Failed to load invoice:', err)
  }
}

const saveDraft = async () => {
  working.value = true
  try {
    await $api(`/v1/admin/billing/invoices/${invoice.value.id}`, {
      method: 'PATCH',
      body: { ...draftForm.value, due_at: draftForm.value.due_at || null },
    })
    notify('Draft saved.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Saving failed.', 'error')
  } finally {
    working.value = false
  }
}

const action = async (name, body = {}) => {
  working.value = true
  try {
    const res = await $api(`/v1/admin/billing/invoices/${invoice.value.id}/${name}`, { method: 'POST', body })

    if (name === 'credit-note') notify(`Credit note ${res.data.invoice_number} issued.`)
    else notify('Done.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'The action failed.', 'error')
  } finally {
    working.value = false
  }
}

const recordPayment = async () => {
  working.value = true
  try {
    await $api(`/v1/admin/billing/invoices/${invoice.value.id}/payments`, {
      method: 'POST',
      body: { amount: Number(paymentForm.value.amount), note: paymentForm.value.note || undefined },
    })
    paymentDialog.value = false
    notify('Payment recorded.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Recording failed.', 'error')
  } finally {
    working.value = false
  }
}

const addReminder = async () => {
  working.value = true
  try {
    await $api(`/v1/admin/billing/invoices/${invoice.value.id}/reminders`, {
      method: 'POST',
      body: reminderForm.value,
    })
    reminderDialog.value = false
    notify('Reminder scheduled.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Scheduling failed.', 'error')
  } finally {
    working.value = false
  }
}

onMounted(async () => {
  await load()

  if (route.query.action === 'payment') paymentDialog.value = true
  if (route.query.action === 'reminder') reminderDialog.value = true
})
</script>

<template>
  <section v-if="invoice">
    <div class="d-flex align-center gap-4 mb-6">
      <VBtn icon="tabler-arrow-left" variant="text" :to="{ name: 'admin-invoices' }" />
      <div>
        <h5 class="text-h5">{{ invoice.invoice_number }}</h5>
        <VChip v-bind="invoiceStatusChip(invoice)" size="small" label>
          {{ invoiceStatusChip(invoice).label }}
        </VChip>
      </div>
      <VSpacer />
      <VBtn v-if="isDraft" color="primary" :loading="working" @click="action('issue')">
        {{ $t('adminInvoices.issue') }}
      </VBtn>
      <VBtn v-if="isDraft" variant="tonal" color="secondary" :loading="working" @click="action('cancel')">
        {{ $t('adminInvoices.cancelDraft') }}
      </VBtn>
      <VBtn
        v-if="['sent', 'awaiting_confirmation', 'paid'].includes(invoice.status)"
        variant="tonal"
        color="warning"
        :loading="working"
        @click="action('credit-note')"
      >
        {{ $t('adminInvoices.creditNote') }}
      </VBtn>
    </div>

    <VRow>
      <!-- Main column -->
      <VCol cols="12" md="8">
        <!-- Read-only banner after issue -->
        <VAlert
          v-if="!isDraft"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('adminInvoices.immutableNote') }}
        </VAlert>

        <!-- Editable header fields (drafts only) -->
        <VCard class="mb-6" :title="$t('adminInvoices.details')">
          <VCardText>
            <VRow>
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="draftForm.reference"
                  :label="$t('billing.reference')"
                  :readonly="!isDraft"
                  class="mb-4"
                />
                <VTextField
                  v-model="draftForm.due_at"
                  :label="$t('billing.dueDate')"
                  type="date"
                  :readonly="!isDraft"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextarea
                  v-model="draftForm.terms"
                  :label="$t('invoice.terms')"
                  rows="2"
                  :readonly="!isDraft"
                  class="mb-4"
                />
                <VTextarea
                  v-model="draftForm.notes"
                  :label="$t('adminInvoices.notes')"
                  rows="2"
                  :readonly="!isDraft"
                />
              </VCol>
            </VRow>
            <VBtn v-if="isDraft" color="primary" variant="tonal" :loading="working" @click="saveDraft">
              {{ $t('adminInvoices.saveDraft') }}
            </VBtn>
          </VCardText>
        </VCard>

        <!-- Lines -->
        <VCard class="mb-6" :title="$t('orders.lines')">
          <VTable class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ $t('invoice.description') }}</th>
                <th class="text-end">{{ $t('services.qty') }}</th>
                <th class="text-end">{{ $t('invoice.unitPrice') }}</th>
                <th class="text-end">{{ $t('invoice.vatPercent') }}</th>
                <th class="text-end">{{ $t('common.total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in invoice.items" :key="item.id">
                <td>{{ item.description }}</td>
                <td class="text-end">{{ item.quantity }}</td>
                <td class="text-end">{{ formatMoney(item.unit_price_net) }}</td>
                <td class="text-end">{{ item.vat_rate }}</td>
                <td class="text-end">{{ formatMoney(item.line_total_gross) }}</td>
              </tr>
              <tr class="font-weight-medium">
                <td colspan="4" class="text-end">Net {{ formatMoney(invoice.subtotal_net) }} · VAT {{ formatMoney(invoice.vat_total) }}</td>
                <td class="text-end">{{ formatMoney(invoice.total_gross) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>

        <!-- Payment history -->
        <VCard class="mb-6" :title="$t('nav.payments')">
          <VCardText v-if="!payments.length" class="text-body-2">
            {{ $t('adminInvoices.noPayments') }}
          </VCardText>
          <VTable v-else class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ $t('payments.provider') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th>{{ $t('payments.method') }}</th>
                <th>{{ $t('common.date') }}</th>
                <th class="text-end">{{ $t('common.amount') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in payments" :key="payment.id">
                <td class="text-capitalize">{{ payment.provider.replace('_', ' ') }}</td>
                <td>{{ payment.status.replaceAll('_', ' ') }}</td>
                <td>{{ payment.method ?? '—' }}</td>
                <td>{{ payment.paid_at ?? '—' }}</td>
                <td class="text-end">{{ formatMoney(payment.amount) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>

        <!-- Audit trail -->
        <VCard :title="$t('adminInvoices.activity')">
          <VCardText>
            <VTimeline density="compact" align="start" truncate-line="both">
              <VTimelineItem
                v-for="(activity, index) in activities"
                :key="index"
                dot-color="primary"
                size="x-small"
              >
                <div class="d-flex justify-space-between flex-wrap gap-2">
                  <span class="font-weight-medium">{{ activity.event.replaceAll('_', ' ') }}</span>
                  <span class="text-body-2">{{ activity.at }}</span>
                </div>
                <div class="text-body-2">{{ activity.actor }}</div>
              </VTimelineItem>
            </VTimeline>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Right rail -->
      <VCol cols="12" md="4">
        <VCard class="mb-6">
          <VCardText>
            <div class="text-body-2">{{ $t('dashboard.outstanding') }}</div>
            <h4 class="text-h4 mb-4">{{ formatMoney(invoice.amount_due) }}</h4>
            <div class="d-flex justify-space-between mb-2">
              <span>{{ $t('common.total') }}</span><span>{{ formatMoney(invoice.total_gross) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-4">
              <span>{{ $t('invoice.paid') }}</span><span>{{ formatMoney(invoice.amount_paid) }}</span>
            </div>
            <VBtn
              block
              color="primary"
              variant="tonal"
              class="mb-3"
              :disabled="invoice.amount_due <= 0"
              @click="paymentDialog = true"
            >
              {{ $t('adminInvoices.manualPayment') }}
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="secondary"
              :disabled="!invoice.due_at"
              @click="reminderDialog = true"
            >
              {{ $t('adminInvoices.addReminder') }}
            </VBtn>
          </VCardText>
        </VCard>

        <!-- Client rail -->
        <VCard class="mb-6" :title="$t('common.client')">
          <VCardText v-if="client">
            <div class="font-weight-medium">{{ client.name }} {{ client.surname }}</div>
            <div class="text-body-2">{{ client.company_name }}</div>
            <div class="text-body-2 mb-2">{{ client.email }}</div>
            <div class="text-body-2">{{ client.address }}</div>
            <div class="text-body-2">{{ client.postal_code }} {{ client.city }} {{ client.country_code }}</div>
            <div v-if="client.vat_id" class="text-body-2 mt-2">UID: {{ client.vat_id }}</div>
          </VCardText>
        </VCard>

        <!-- Reminder rail -->
        <VCard :title="$t('adminInvoices.reminders')">
          <VCardText v-if="!reminders.length" class="text-body-2">
            {{ $t('adminInvoices.noReminders') }}
          </VCardText>
          <VList v-else density="compact">
            <VListItem v-for="reminder in reminders" :key="reminder.id">
              <VListItemTitle>
                {{ reminder.offset_days >= 0 ? `${reminder.offset_days} days after due` : `${-reminder.offset_days} days before due` }}
              </VListItemTitle>
              <VListItemSubtitle>
                {{ formatDate(reminder.scheduled_for) }} · {{ reminder.channel }}
                <VChip v-if="reminder.sent_at" size="x-small" color="success" label class="ms-1">sent</VChip>
              </VListItemSubtitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <!-- Manual payment dialog -->
    <VDialog v-model="paymentDialog" max-width="420">
      <VCard title="Record manual payment">
        <VCardText>
          <VTextField
            v-model="paymentForm.amount"
            label="Amount"
            type="number"
            min="0.01"
            suffix="EUR"
            class="mb-4"
          />
          <VTextarea v-model="paymentForm.note" :label="$t('checkout.noteOptional')" rows="2" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="paymentDialog = false">{{ $t('common.cancel') }}</VBtn>
          <VBtn color="primary" :loading="working" @click="recordPayment">{{ $t('adminInvoices.record') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Reminder dialog -->
    <VDialog v-model="reminderDialog" max-width="420">
      <VCard title="Add reminder">
        <VCardText>
          <VSelect
            v-model="reminderForm.offset_days"
            :items="[
              { title: '3 days after due', value: 3 },
              { title: '7 days after due', value: 7 },
              { title: '14 days after due', value: 14 },
              { title: 'Final notice (21 days)', value: 21 },
            ]"
            :label="$t('adminInvoices.when')"
            class="mb-4"
          />
          <VSelect
            v-model="reminderForm.channel"
            :items="[
              { title: 'Email', value: 'email' },
              { title: 'Push', value: 'push' },
              { title: 'Email + push', value: 'both' },
            ]"
            :label="$t('adminInvoices.channel')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="reminderDialog = false">{{ $t('common.cancel') }}</VBtn>
          <VBtn color="primary" :loading="working" @click="addReminder">{{ $t('adminInvoices.schedule') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>
