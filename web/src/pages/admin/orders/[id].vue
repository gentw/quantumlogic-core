<script setup>
const route = useRoute('admin-orders-id')

const order = ref(null)
const client = ref(null)
const depositSplit = ref(null)
const working = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

const notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

// Which transition each status offers next.
const nextActions = computed(() => ({
  draft: [{ action: 'submit', label: 'Submit for payment', color: 'primary' }],
  awaiting_payment: [{ action: 'activate', label: 'Mark active', color: 'primary' }],
  active: [{ action: 'startDelivery', label: 'Start delivery', color: 'primary' }],
  in_delivery: [{ action: 'complete', label: 'Complete', color: 'success' }],
}[order.value?.status] ?? []))

const canCancel = computed(() => order.value && !['completed', 'cancelled'].includes(order.value.status))

const load = async () => {
  try {
    const res = await $api(`/v1/admin/billing/orders/${route.params.id}`)

    order.value = res.order
    client.value = res.client
    depositSplit.value = res.deposit_split
  } catch (err) {
    console.error('Failed to load the order:', err)
  }
}

onMounted(load)

const transition = async action => {
  working.value = true
  try {
    await $api(`/v1/admin/billing/orders/${order.value.id}/transition`, {
      method: 'POST',
      body: { action },
    })
    notify('Order updated.')
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'The transition failed.', 'error')
  } finally {
    working.value = false
  }
}
</script>

<template>
  <section v-if="order">
    <div class="d-flex align-center gap-4 mb-6 flex-wrap">
      <VBtn icon="tabler-arrow-left" variant="text" :to="{ name: 'admin-orders' }" />
      <div>
        <h5 class="text-h5">{{ order.order_number }}</h5>
        <VChip size="small" label>{{ order.status.replaceAll('_', ' ') }}</VChip>
      </div>
      <VSpacer />
      <VBtn
        v-for="next in nextActions"
        :key="next.action"
        :color="next.color"
        :loading="working"
        @click="transition(next.action)"
      >
        {{ next.label }}
      </VBtn>
      <VBtn
        v-if="canCancel"
        variant="tonal"
        color="error"
        :loading="working"
        @click="transition('cancel')"
      >
        {{ $t('orders.cancelOrder') }}
      </VBtn>
    </div>

    <VRow>
      <VCol cols="12" md="8">
        <VCard class="mb-6" :title="$t('orders.lines')">
          <VTable class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ $t('services.service') }}</th>
                <th class="text-end">{{ $t('services.qty') }}</th>
                <th class="text-end">{{ $t('common.total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id">
                <td>{{ item.description }}</td>
                <td class="text-end">{{ item.quantity }}{{ item.unit ? ` ${item.unit}` : '' }}</td>
                <td class="text-end">{{ formatMoney(item.line_total_gross) }}</td>
              </tr>
              <tr class="font-weight-medium">
                <td colspan="2" class="text-end">
                  Net {{ formatMoney(order.subtotal_net) }} · VAT {{ formatMoney(order.vat_total) }}
                </td>
                <td class="text-end">{{ formatMoney(order.total_gross) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>

        <VCard :title="$t('nav.invoices')">
          <VCardText v-if="!order.invoices?.length" class="text-body-2">
            {{ $t('orders.nothingInvoiced') }}
            <RouterLink :to="{ name: 'admin-invoices-add-invoice' }">{{ $t('adminInvoices.new') }}</RouterLink>.
          </VCardText>
          <VTable v-else class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ $t('common.nr') }}</th>
                <th>{{ $t('invoice.type') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th class="text-end">{{ $t('common.amount') }}</th>
                <th class="text-end">{{ $t('dashboard.outstanding') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="invoice in order.invoices" :key="invoice.id">
                <td>
                  <RouterLink
                    :to="{ name: 'admin-invoices-id', params: { id: invoice.id } }"
                    class="text-link"
                  >
                    {{ invoice.invoice_number }}
                  </RouterLink>
                </td>
                <td>{{ invoiceTypeLabel(invoice.type) }}</td>
                <td>
                  <VChip v-bind="invoiceStatusChip(invoice)" size="x-small" label>
                    {{ invoiceStatusChip(invoice).label }}
                  </VChip>
                </td>
                <td class="text-end">{{ formatMoney(invoice.total_gross) }}</td>
                <td class="text-end">{{ formatMoney(invoice.amount_due) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard class="mb-6" :title="$t('common.client')">
          <VCardText v-if="client">
            <div class="font-weight-medium">{{ client.name }} {{ client.surname }}</div>
            <div class="text-body-2">{{ client.email }}</div>
          </VCardText>
        </VCard>

        <VCard v-if="depositSplit" :title="$t('orders.depositSplit')">
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>Deposit ({{ order.deposit_percent ?? 50 }}%)</span>
              <span class="font-weight-medium">{{ formatMoney(depositSplit.deposit) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span>{{ $t('orders.balance') }}</span>
              <span class="font-weight-medium">{{ formatMoney(depositSplit.balance) }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>
