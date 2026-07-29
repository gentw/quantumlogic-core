<script setup>
const services = ref([])
const loading = ref(false)
const working = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

// Create/edit dialog — one form for both
const dialog = ref(false)
const editing = ref(null)
const form = ref({})

const blankForm = () => ({
  name: '',
  description: '',
  category: '',
  billing_type: 'one_off',
  default_price_net: 0,
  default_billing_interval: null,
  vat_rate: 20,
  supports_deposit: false,
  default_deposit_percent: null,
  is_publicly_orderable: false,
  active: true,
  sort_order: 0,
})

const notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

const load = async () => {
  loading.value = true
  try {
    services.value = (await $api('/v1/admin/billing/services', { query: { all: 1 } })).data
  } catch (err) {
    console.error('Failed to load the catalogue:', err)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const openCreate = () => {
  editing.value = null
  form.value = blankForm()
  dialog.value = true
}

const openEdit = service => {
  editing.value = service
  form.value = { ...service }
  dialog.value = true
}

const save = async () => {
  working.value = true
  try {
    if (editing.value) {
      await $api(`/v1/admin/billing/services/${editing.value.id}`, { method: 'PATCH', body: form.value })
      notify('Service updated.')
    } else {
      await $api('/v1/admin/billing/services', { method: 'POST', body: form.value })
      notify('Service created.')
    }
    dialog.value = false
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Saving failed.', 'error')
  } finally {
    working.value = false
  }
}

const deactivate = async service => {
  working.value = true
  try {
    await $api(`/v1/admin/billing/services/${service.id}/deactivate`, { method: 'POST' })
    notify(`${service.name} deactivated.`)
    await load()
  } catch (err) {
    notify(err.data?.message ?? 'Deactivating failed.', 'error')
  } finally {
    working.value = false
  }
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Service catalogue</VCardTitle>
      <VCardSubtitle>What the agency sells — orders and guest checkout build on these</VCardSubtitle>
      <template #append>
        <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">
          New service
        </VBtn>
      </template>
    </VCardItem>

    <VTable class="text-no-wrap">
      <thead>
        <tr>
          <th>Service</th>
          <th>Billing</th>
          <th class="text-end">Net price</th>
          <th class="text-end">VAT %</th>
          <th>Deposit</th>
          <th>Public</th>
          <th>Active</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="service in services" :key="service.id">
          <td>
            <div class="font-weight-medium">{{ service.name }}</div>
            <div class="text-body-2">{{ service.category }}</div>
          </td>
          <td>
            {{ service.billing_type.replace('_', '-') }}{{ service.default_billing_interval ? ` / ${service.default_billing_interval}` : '' }}
          </td>
          <td class="text-end">{{ formatMoney(service.default_price_net) }}</td>
          <td class="text-end">{{ service.vat_rate }}</td>
          <td>{{ service.supports_deposit ? `${service.default_deposit_percent ?? 50}%` : '—' }}</td>
          <td><VIcon :icon="service.is_publicly_orderable ? 'tabler-world' : 'tabler-lock'" size="18" /></td>
          <td>
            <VChip size="x-small" label :color="service.active ? 'success' : 'secondary'">
              {{ service.active ? 'active' : 'inactive' }}
            </VChip>
          </td>
          <td class="text-end">
            <VBtn icon="tabler-pencil" size="small" variant="text" @click="openEdit(service)" />
            <VBtn
              v-if="service.active"
              icon="tabler-circle-off"
              size="small"
              variant="text"
              color="error"
              :disabled="working"
              @click="deactivate(service)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Create/edit dialog -->
    <VDialog v-model="dialog" max-width="640">
      <VCard :title="editing ? `Edit ${editing.name}` : 'New service'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.name" label="Name" class="mb-3" />
              <VTextField v-model="form.category" label="Category" class="mb-3" />
              <VSelect
                v-model="form.billing_type"
                :items="[
                  { title: 'One-off', value: 'one_off' },
                  { title: 'Recurring', value: 'recurring' },
                  { title: 'Milestone', value: 'milestone' },
                ]"
                label="Billing type"
                class="mb-3"
              />
              <VSelect
                v-if="form.billing_type === 'recurring'"
                v-model="form.default_billing_interval"
                :items="[
                  { title: 'Monthly', value: 'monthly' },
                  { title: 'Yearly', value: 'yearly' },
                ]"
                label="Interval"
                class="mb-3"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.default_price_net" label="Default net price" type="number" suffix="EUR" class="mb-3" />
              <VTextField v-model.number="form.vat_rate" label="VAT %" type="number" class="mb-3" />
              <VSwitch v-model="form.supports_deposit" label="Supports deposit" class="mb-1" />
              <VTextField
                v-if="form.supports_deposit"
                v-model.number="form.default_deposit_percent"
                label="Default deposit %"
                type="number"
                class="mb-3"
              />
              <VSwitch v-model="form.is_publicly_orderable" label="Publicly orderable" class="mb-1" />
              <VSwitch v-model="form.active" label="Active" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.description" label="Description" rows="2" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="dialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="working" :disabled="!form.name" @click="save">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top end">
      {{ snackbar.text }}
    </VSnackbar>
  </VCard>
</template>
