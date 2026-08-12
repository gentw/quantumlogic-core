<script setup>
import html2pdf from 'html2pdf.js'

const route = useRoute('client-billing-invoices-id')
const billingApi = useBillingApi()

const invoice = ref(null)
const bankDetails = ref(null)
const loadError = ref(false)

const isPayable = computed(() =>
  invoice.value
  && invoice.value.amount_due > 0
  && ['sent', 'awaiting_confirmation', 'unpaid'].includes(invoice.value.status),
)

const load = async () => {
  try {
    invoice.value = await billingApi.invoice(route.params.id)

    // The EPC QR replaces the mock's decorative barcode — only issued,
    // payable invoices have one.
    if (isPayable.value)
      bankDetails.value = await billingApi.bankDetails(invoice.value.id)
  } catch (err) {
    console.error('Failed to load invoice:', err)
    loadError.value = true
  }
}

const downloadPdf = () => {
  const element = document.querySelector('.invoice-detail-card')
  if (!element) return

  html2pdf().set({
    margin: 0.5,
    filename: `${invoice.value.invoice_number}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 1, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
  }).from(element).save()
}

onMounted(load)
</script>

<template>
  <section v-if="invoice">
    <div class="d-flex align-center mb-6 gap-4">
      <VBtn icon="tabler-arrow-left" variant="text" :to="{ name: 'client-billing' }" />
      <div>
        <div class="text-body-2">Invoice</div>
        <h5 class="text-h5">{{ invoice.invoice_number }}</h5>
      </div>
      <VSpacer />
      <VBtn variant="text" append-icon="tabler-download" @click="downloadPdf">
        {{ $t('invoice.download') }}
      </VBtn>
    </div>

    <VRow>
      <!-- Invoice details -->
      <VCol cols="12" md="8">
        <VCard class="invoice-detail-card">
          <VCardText>
            <div class="d-flex justify-space-between align-start flex-wrap gap-4 mb-6">
              <h6 class="text-h6">{{ $t('invoice.details') }}</h6>
              <img
                v-if="bankDetails?.epc_qr_png"
                :src="bankDetails.epc_qr_png"
                alt="EPC QR — scan with your banking app"
                width="96"
                height="96"
              >
            </div>

            <VRow>
              <VCol cols="12" sm="6">
                <div class="mb-4">
                  <div class="text-body-2">{{ $t('invoice.date') }}</div>
                  <div class="font-weight-medium">{{ formatDate(invoice.issued_at) }}</div>
                </div>
                <div class="mb-4">
                  <div class="text-body-2">{{ $t('billing.dueDate') }}</div>
                  <div class="font-weight-medium">{{ formatDate(invoice.due_at) }}</div>
                </div>
                <div v-if="invoice.order_number" class="mb-4">
                  <div class="text-body-2">{{ $t('invoice.order') }}</div>
                  <div class="font-weight-medium">{{ invoice.order_number }}</div>
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div v-if="invoice.reference" class="mb-4">
                  <div class="text-body-2">{{ $t('billing.reference') }}</div>
                  <div class="font-weight-medium">{{ invoice.reference }}</div>
                </div>
                <div v-if="invoice.terms" class="mb-4">
                  <div class="text-body-2">{{ $t('invoice.terms') }}</div>
                  <div class="font-weight-medium">{{ invoice.terms }}</div>
                </div>
                <div class="mb-4">
                  <div class="text-body-2">{{ $t('invoice.type') }}</div>
                  <div class="font-weight-medium">{{ invoiceTypeLabel(invoice.type) }}</div>
                </div>
              </VCol>
            </VRow>

            <VAlert
              v-if="invoice.reverse_charge"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              Reverse charge — Steuerschuldnerschaft des Leistungsempfängers (Art. 196 MwStSystRL)
            </VAlert>

            <!-- Line items -->
            <VTable class="border rounded overflow-hidden">
              <thead>
                <tr>
                  <th>{{ $t('invoice.description') }}</th>
                  <th class="text-end">{{ $t('services.qty') }}</th>
                  <th class="text-end">{{ $t('invoice.unitPrice') }}</th>
                  <th class="text-end">{{ $t('invoice.discountPercent') }}</th>
                  <th class="text-end">{{ $t('invoice.vatPercent') }}</th>
                  <th class="text-end">{{ $t('common.total') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in invoice.items" :key="item.id">
                  <td>{{ item.description }}</td>
                  <td class="text-end">{{ item.quantity }}{{ item.unit ? ` ${item.unit}` : '' }}</td>
                  <td class="text-end">{{ formatMoney(item.unit_price_net) }}</td>
                  <td class="text-end">{{ item.discount_percent }}</td>
                  <td class="text-end">{{ item.vat_rate }}</td>
                  <td class="text-end">{{ formatMoney(item.line_total_gross) }}</td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Amount panel -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <div class="text-body-2">{{ $t('billing.amountDue') }}</div>
                <h4 class="text-h4">{{ formatMoney(invoice.amount_due) }}</h4>
              </div>
              <VChip v-bind="invoiceStatusChip(invoice)" size="small" label>
                {{ invoiceStatusChip(invoice).label }}
              </VChip>
            </div>

            <VDivider class="mb-4" />

            <div class="d-flex justify-space-between mb-2">
              <span>{{ $t('invoice.netAmount') }}</span>
              <span>{{ formatMoney(invoice.subtotal_net) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>{{ $t('invoice.discount') }}</span>
              <span>{{ formatMoney(invoice.discount_total) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>{{ $t('invoice.vat') }}</span>
              <span>{{ formatMoney(invoice.vat_total) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2 font-weight-medium">
              <span>{{ $t('common.total') }}</span>
              <span>{{ formatMoney(invoice.total_gross) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-4">
              <span>{{ $t('invoice.paid') }}</span>
              <span>{{ formatMoney(invoice.amount_paid) }}</span>
            </div>

            <VBtn
              v-if="isPayable"
              block
              color="primary"
              append-icon="tabler-chevron-right"
              :to="{ name: 'client-billing-checkout-id', params: { id: invoice.id } }"
            >
              {{ $t('dashboard.payNow') }}
            </VBtn>

            <p v-if="invoice.status === 'awaiting_confirmation'" class="text-body-2 mt-4 mb-0">
              {{ $t('invoice.proofUnderReview') }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </section>

  <section v-else-if="loadError">
    <VAlert type="error" variant="tonal">
      {{ $t('invoice.loadFailed') }}
    </VAlert>
  </section>
</template>
