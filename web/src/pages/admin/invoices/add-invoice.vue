<script setup>
const router = useRouter()

const step = ref(1)
const working = ref(false)
const errorMessage = ref('')

// 1 — client
const clientSearch = ref('')
const clientOptions = ref([])
const client = ref(null)

// 2 — order (existing or built from the catalogue)
const orderMode = ref('existing')
const clientOrders = ref([])
const selectedOrderId = ref(null)
const services = ref([])
const lines = ref([{ service_id: null, description: '', quantity: 1, unit_price_net: null, discount_percent: 0 }])
const depositPercent = ref(50)

// 3 — invoice type
const invoiceType = ref('one_off')
const depositFraction = ref(50)

// 4 — details
const details = ref({ due_at: '', reference: '', terms: '', notes: '' })

// 5 — preview
const draft = ref(null)

const typeOptions = [
  { title: 'Deposit (%)', value: 'deposit' },
  { title: 'Milestone', value: 'milestone' },
  { title: 'Balance', value: 'balance' },
  { title: 'One-off', value: 'one_off' },
  { title: 'Recurring', value: 'recurring' },
]

let searchTimeout
watch(clientSearch, term => {
  clearTimeout(searchTimeout)
  if (!term || term.length < 2) return
  searchTimeout = setTimeout(async () => {
    try {
      clientOptions.value = await $api('/v1/admin/notifReminders/findUserByName', {
        method: 'POST',
        body: { user: term },
      })
    } catch (err) {
      console.error('Client search failed:', err)
    }
  }, 300)
})

const pickClient = async selected => {
  if (!selected) return
  client.value = selected
  try {
    const res = await $api(`/v1/admin/billing/clients/${selected.id}/orders`)

    clientOrders.value = res.data
    orderMode.value = clientOrders.value.length ? 'existing' : 'new'
    if (!services.value.length)
      services.value = (await $api('/v1/admin/billing/services')).data
    step.value = 2
  } catch (err) {
    console.error('Failed to load client orders:', err)
  }
}

const addLine = () => {
  lines.value.push({ service_id: null, description: '', quantity: 1, unit_price_net: null, discount_percent: 0 })
}

const onServicePicked = line => {
  const service = services.value.find(s => s.id === line.service_id)

  if (service) {
    line.description = line.description || service.name
    line.unit_price_net = line.unit_price_net ?? Number(service.default_price_net)
  }
}

const resolveOrder = async () => {
  errorMessage.value = ''
  working.value = true
  try {
    if (orderMode.value === 'new') {
      const res = await $api('/v1/admin/billing/orders', {
        method: 'POST',
        body: {
          user_id: client.value.id,
          lines: lines.value.filter(line => line.service_id || line.description),
          deposit_percent: depositPercent.value,
        },
      })

      selectedOrderId.value = res.data.id
    }

    if (!selectedOrderId.value) {
      errorMessage.value = 'Pick or build an order first.'

      return
    }

    step.value = 3
  } catch (err) {
    errorMessage.value = err.data?.message ?? 'The order could not be created.'
  } finally {
    working.value = false
  }
}

const createDraft = async () => {
  errorMessage.value = ''
  working.value = true
  try {
    const res = await $api('/v1/admin/billing/invoices', {
      method: 'POST',
      body: {
        service_order_id: selectedOrderId.value,
        type: invoiceType.value,
        fraction: invoiceType.value === 'deposit' ? depositFraction.value / 100 : 1,
      },
    })

    draft.value = res.data

    // Apply step-4 details onto the draft.
    const patch = {
      reference: details.value.reference || null,
      terms: details.value.terms || null,
      notes: details.value.notes || null,
      due_at: details.value.due_at || null,
    }

    if (Object.values(patch).some(value => value !== null)) {
      const updated = await $api(`/v1/admin/billing/invoices/${draft.value.id}`, { method: 'PATCH', body: patch })

      draft.value = updated.data
    }

    step.value = 5
  } catch (err) {
    errorMessage.value = err.data?.message ?? 'The draft could not be created.'
  } finally {
    working.value = false
  }
}

const finish = async issueNow => {
  working.value = true
  try {
    if (issueNow)
      await $api(`/v1/admin/billing/invoices/${draft.value.id}/issue`, { method: 'POST', body: {} })

    router.push({ name: 'admin-invoices-id', params: { id: draft.value.id } })
  } catch (err) {
    errorMessage.value = err.data?.message ?? 'Issuing failed.'
    working.value = false
  }
}
</script>

<template>
  <VCard title="New invoice">
    <VCardText>
      <VStepper v-model="step" :items="['Client', 'Order', 'Type', 'Details', 'Preview']" hide-actions>
        <!-- 1: pick a client -->
        <template #item.1>
          <VAutocomplete
            v-model:search="clientSearch"
            :items="clientOptions"
            item-title="name"
            return-object
            label="Search clients by name"
            no-filter
            class="mb-4"
            @update:model-value="pickClient"
          />
          <p class="text-body-2">
            New client? Create them under
            <RouterLink :to="{ name: 'admin-clients-add-client' }">Clients</RouterLink>
            first — guest checkout accounts also land there.
          </p>
        </template>

        <!-- 2: pick or build an order -->
        <template #item.2>
          <VRadioGroup v-model="orderMode" inline class="mb-4">
            <VRadio value="existing" label="Existing order" :disabled="!clientOrders.length" />
            <VRadio value="new" label="New order from the catalogue" />
          </VRadioGroup>

          <VSelect
            v-if="orderMode === 'existing'"
            v-model="selectedOrderId"
            :items="clientOrders"
            :item-title="order => `${order.order_number} — ${formatMoney(order.total_gross)} (${order.status})`"
            item-value="id"
            label="Order"
            class="mb-4"
          />

          <template v-else>
            <div v-for="(line, index) in lines" :key="index" class="d-flex flex-wrap gap-3 align-center mb-3">
              <VSelect
                v-model="line.service_id"
                :items="services"
                item-title="name"
                item-value="id"
                label="Service"
                density="compact"
                style="min-inline-size: 14rem;"
                @update:model-value="onServicePicked(line)"
              />
              <VTextField v-model="line.description" label="Description" density="compact" style="min-inline-size: 14rem;" />
              <VTextField v-model.number="line.quantity" label="Qty" type="number" min="0.01" density="compact" style="inline-size: 6rem;" />
              <VTextField v-model.number="line.unit_price_net" label="Unit net" type="number" density="compact" suffix="EUR" style="inline-size: 9rem;" />
              <VTextField v-model.number="line.discount_percent" label="Disc %" type="number" density="compact" style="inline-size: 6rem;" />
              <VBtn icon="tabler-trash" size="small" variant="text" color="error" :disabled="lines.length === 1" @click="lines.splice(index, 1)" />
            </div>
            <VBtn variant="tonal" size="small" prepend-icon="tabler-plus" @click="addLine">
              Add line
            </VBtn>
          </template>

          <div class="d-flex justify-space-between mt-6">
            <VBtn variant="text" color="secondary" @click="step = 1">Back</VBtn>
            <VBtn color="primary" :loading="working" @click="resolveOrder">Continue</VBtn>
          </div>
        </template>

        <!-- 3: invoice type -->
        <template #item.3>
          <VSelect v-model="invoiceType" :items="typeOptions" label="Invoice type" class="mb-4" />
          <VTextField
            v-if="invoiceType === 'deposit'"
            v-model.number="depositFraction"
            label="Deposit percent"
            type="number"
            min="1"
            max="100"
            suffix="%"
            style="max-inline-size: 12rem;"
            class="mb-4"
          />
          <p v-if="invoiceType === 'balance'" class="text-body-2">
            The balance invoice takes exactly what the order's other invoices
            haven't billed yet.
          </p>
          <div class="d-flex justify-space-between mt-6">
            <VBtn variant="text" color="secondary" @click="step = 2">Back</VBtn>
            <VBtn color="primary" @click="step = 4">Continue</VBtn>
          </div>
        </template>

        <!-- 4: details -->
        <template #item.4>
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField v-model="details.due_at" label="Due date (defaults to NET 14)" type="date" class="mb-4" />
              <VTextField v-model="details.reference" label="Reference" class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextarea v-model="details.terms" label="Terms" rows="2" class="mb-4" />
              <VTextarea v-model="details.notes" label="Notes" rows="2" />
            </VCol>
          </VRow>
          <div class="d-flex justify-space-between mt-2">
            <VBtn variant="text" color="secondary" @click="step = 3">Back</VBtn>
            <VBtn color="primary" :loading="working" @click="createDraft">Preview</VBtn>
          </div>
        </template>

        <!-- 5: preview -->
        <template #item.5>
          <template v-if="draft">
            <VTable class="border rounded mb-4">
              <tbody>
                <tr><td>Type</td><td class="text-end">{{ invoiceTypeLabel(draft.type) }}</td></tr>
                <tr><td>Net</td><td class="text-end">{{ formatMoney(draft.subtotal_net) }}</td></tr>
                <tr><td>VAT</td><td class="text-end">{{ formatMoney(draft.vat_total) }}</td></tr>
                <tr class="font-weight-medium"><td>Gross</td><td class="text-end">{{ formatMoney(draft.total_gross) }}</td></tr>
              </tbody>
            </VTable>
            <VAlert v-if="draft.reverse_charge" type="info" variant="tonal" density="compact" class="mb-4">
              Reverse charge applies — 0% VAT with the Art. 196 note.
            </VAlert>
            <div class="d-flex justify-end gap-3">
              <VBtn variant="tonal" color="secondary" :loading="working" @click="finish(false)">
                Save as draft
              </VBtn>
              <VBtn color="primary" :loading="working" @click="finish(true)">
                Issue now
              </VBtn>
            </div>
          </template>
        </template>
      </VStepper>

      <VAlert v-if="errorMessage" type="error" variant="tonal" density="compact" class="mt-4">
        {{ errorMessage }}
      </VAlert>
    </VCardText>
  </VCard>
</template>
