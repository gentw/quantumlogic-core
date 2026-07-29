<script setup>
/**
 * Billing summary card for the admin client detail page: lifetime value,
 * outstanding balance, recent invoices. Self-contained so the legacy page
 * only needs to render it.
 */
const props = defineProps({
  clientId: {
    type: [Number, String],
    required: true,
  },
})

const summary = ref(null)

onMounted(async () => {
  try {
    summary.value = await $api(`/v1/admin/billing/clients/${props.clientId}/summary`)
  } catch (err) {
    console.error('Failed to load the client billing summary:', err)
  }
})
</script>

<template>
  <VCard v-if="summary" title="Billing" class="mt-6">
    <VCardText>
      <div class="d-flex flex-wrap gap-6 mb-4">
        <div>
          <div class="text-body-2">Lifetime value</div>
          <h6 class="text-h6">{{ formatMoney(summary.lifetime_value) }}</h6>
        </div>
        <div>
          <div class="text-body-2">Outstanding</div>
          <h6 class="text-h6">{{ formatMoney(summary.outstanding) }}</h6>
        </div>
        <div>
          <div class="text-body-2">Invoices</div>
          <h6 class="text-h6">{{ summary.invoice_count }}</h6>
        </div>
        <div>
          <div class="text-body-2">Saved methods</div>
          <h6 class="text-h6">{{ summary.payment_method_count }}</h6>
        </div>
      </div>

      <VTable v-if="summary.recent_invoices.length" density="compact" class="border rounded">
        <tbody>
          <tr v-for="invoice in summary.recent_invoices" :key="invoice.id">
            <td>
              <RouterLink
                :to="{ name: 'admin-invoices-id', params: { id: invoice.id } }"
                class="text-link"
              >
                {{ invoice.invoice_number }}
              </RouterLink>
            </td>
            <td>
              <VChip v-bind="invoiceStatusChip(invoice)" size="x-small" label>
                {{ invoiceStatusChip(invoice).label }}
              </VChip>
            </td>
            <td class="text-end">{{ formatMoney(invoice.total_gross) }}</td>
            <td class="text-end">{{ formatMoney(invoice.amount_due) }} open</td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
