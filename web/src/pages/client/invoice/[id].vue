<script setup>
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import html2pdf from 'html2pdf.js'

const downloadPDF = () => {
  const element = document.querySelector('.invoice-preview-wrapper')
  if (!element) return

  const opt = {
    margin:       0.5,
    filename:     `QuantumLogic_Invoice_${invoice.value.id}.pdf`,
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 1, useCORS: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }

  html2pdf().set(opt).from(element).save()
}

const route = useRoute('apps-invoice-preview-id')

// Sidebars
const isAddPaymentSidebarVisible = ref(false)
const isSendPaymentSidebarVisible = ref(false)

// Dummy invoice & payment data (replace with real API later)
const invoice = ref({
  id: Math.floor(Math.random() * 1000),
  issuedDate: new Date(),
  dueDate: new Date(new Date().setDate(new Date().getDate() + 7)),
  client: {
    name: 'John Doe',
    company: 'Doe Enterprises',
    address: '123 Elm Street',
    country: 'USA',
    contact: '+1 234 567 890',
    companyEmail: 'john.doe@example.com',
  },
})


const paymentDetails = ref({
  totalDue: '$34',
  bankName: 'Bank of QuantumLogic',
  country: 'USA',
  iban: 'SG1234567890',
  swiftCode: 'SGATEUS33',
})

// Purchased package(s) (only the selected package)
const purchasedProducts = ref([
  {
    name: 'Professional Package',
    description: 'Security & optimization for growing sites (monthly)',
    qty: 1,
    hours: 0,
    price: 34,
  },
])

const printInvoice = () => {
  window.print()
}

// Navigate to payment for selected package
const proceedToPayment = (product) => {
  alert(`Proceed to payment for ${product.name}`)
}

// Dynamically detect total
const totalAmount = computed(() => purchasedProducts.value.reduce((acc, i) => acc + i.price, 0))
</script>

<template>
  <section v-if="invoice && paymentDetails">
    <VRow>
      <!-- Invoice Preview -->
      <VCol cols="12" md="9">
        <VCard class="invoice-preview-wrapper pa-6 pa-sm-12">
          <!-- Header -->
          <div class="invoice-header-preview d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row gap-6 rounded pa-6 mb-6 bg-var-theme-background">
            <div>
              <div class="d-flex align-center app-logo mb-6">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
              </div>
              <h6 class="text-h6 font-weight-regular">Office 149, 450 South Brand Brooklyn</h6>
              <h6 class="text-h6 font-weight-regular">San Diego County, CA 91905, USA</h6>
              <h6 class="text-h6 font-weight-regular">+1 (123) 456 7891, +44 (876) 543 2198</h6>
            </div>
            <div>
              <h6 class="font-weight-medium text-lg mb-6">Invoice #{{ invoice.id }}</h6>
              <h6 class="text-h6 font-weight-regular"><span>Date Issued: </span>{{ new Date(invoice.issuedDate).toLocaleDateString('en-GB') }}</h6>
              <h6 class="text-h6 font-weight-regular"><span>Due Date: </span>{{ new Date(invoice.dueDate).toLocaleDateString('en-GB') }}</h6>
            </div>
          </div>

          <!-- Invoice To / Payment Details -->
          <VRow class="print-row mb-6">
            <VCol>
              <h6 class="text-h6 mb-4">Invoice To:</h6>
              <p class="mb-0">{{ invoice.client.name }}</p>
              <p class="mb-0">{{ invoice.client.company }}</p>
              <p class="mb-0">{{ invoice.client.address }}, {{ invoice.client.country }}</p>
              <p class="mb-0">{{ invoice.client.contact }}</p>
              <p class="mb-0">{{ invoice.client.companyEmail }}</p>
            </VCol>
            <VCol>
              <h6 class="text-h6 mb-4">Payment Details:</h6>
              <table>
                <tbody>
                  <tr><td class="pe-4">Total Due:</td><td>{{ paymentDetails.totalDue }}</td></tr>
                  <tr><td class="pe-4">Bank Name:</td><td>{{ paymentDetails.bankName }}</td></tr>
                  <tr><td class="pe-4">Country:</td><td>{{ paymentDetails.country }}</td></tr>
                  <tr><td class="pe-4">IBAN:</td><td>{{ paymentDetails.iban }}</td></tr>
                  <tr><td class="pe-4">SWIFT:</td><td>{{ paymentDetails.swiftCode }}</td></tr>
                </tbody>
              </table>
            </VCol>
          </VRow>

          <!-- Services Table -->
          <VTable class="invoice-preview-table border text-high-emphasis overflow-hidden mb-6">
            <thead>
              <tr>
                <th>ITEM</th>
                <th>DESCRIPTION</th>
                <th class="text-center">QTY</th>
                <th class="text-center">TOTAL</th>
              </tr>
            </thead>
            <tbody class="text-base">
              <tr v-for="item in purchasedProducts" :key="item.name">
                <td>{{ item.name }}</td>
                <td>{{ item.description }}</td>
                <td class="text-center">{{ item.qty }}</td>
                <td class="text-center">${{ item.price }}</td>
              </tr>
            </tbody>
          </VTable>

          <!-- Total Summary -->
          <div class="d-flex justify-space-between flex-column flex-sm-row print-row">
            <div class="mb-2">
              <div class="d-flex align-center mb-1">
                <h6 class="text-h6 me-2">Salesperson:</h6>
                <span>Jenny Parker</span>
              </div>
              <p>Thanks for using QuantumLogic services!</p>
            </div>
            <div>
              <table class="w-100">
                <tbody>
                  <tr><td class="pe-16">Subtotal:</td><td class="text-end">${{ totalAmount }}</td></tr>
                  <tr><td class="pe-16">Discount:</td><td class="text-end">$0</td></tr>
                  <tr><td class="pe-16">Tax:</td><td class="text-end">0%</td></tr>
                  <tr><td class="pe-16">Total:</td><td class=""><h2>${{ totalAmount }}</h2></td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <VDivider class="my-6 border-dashed" />
          <p class="mb-0"><span class="text-high-emphasis font-weight-medium me-1">Note:</span>
          Your QuantumLogic services are active. Enjoy your plan!</p>
        </VCard>
      </VCol>

      <!-- Actions / Buttons -->
      <VCol cols="12" md="3" class="d-print-none">
        <VCard>
          <VCardText>
            <!-- Pay for selected package -->
            <VBtn block color="success" prepend-icon="tabler-currency-dollar" class="mb-4" @click="isAddPaymentSidebarVisible = true">
              Pay Now
            </VBtn>

            <!-- Upgrade package (select first package purchased as default) -->
            <VBtn block color="primary" prepend-icon="tabler-credit-card" class="mb-4" @click="() => proceedToPayment(purchasedProducts[0])">
              Upgrade Plan
            </VBtn>

            <!-- Print invoice -->
            <VBtn block color="secondary" variant="tonal" class="mb-4" @click="printInvoice">
              Print
            </VBtn>

            <!-- Download PDF -->
            <VBtn block color="info" prepend-icon="tabler-download" class="mb-4" @click="downloadPDF">
            Download PDF
            </VBtn>

            <!-- Send invoice -->
            <VBtn block color="warning" prepend-icon="tabler-send" class="mb-4" @click="isSendPaymentSidebarVisible = true">
              Send Invoice
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Sidebars -->
    <InvoiceAddPaymentDrawer v-model:isDrawerOpen="isAddPaymentSidebarVisible" />
    <InvoiceSendInvoiceDrawer v-model:isDrawerOpen="isSendPaymentSidebarVisible" />
  </section>

  <section v-else>
    <VAlert type="error" variant="tonal">Invoice with ID {{ route.params.id }} not found!</VAlert>
  </section>
</template>

<style lang="scss">
.invoice-preview-table {
  --v-table-header-color: var(--v-theme-surface);
  &.v-table .v-table__wrapper table thead tr th {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}
</style>
