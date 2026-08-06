<script setup>
const orders = ref([])
const plans = ref([])
const loading = ref(true)

const orderStatusChip = status => {
  const map = {
    awaiting_payment: { color: 'warning', label: 'Awaiting payment' },
    active: { color: 'info', label: 'Active' },
    in_delivery: { color: 'primary', label: 'In delivery' },
    completed: { color: 'success', label: 'Completed' },
    cancelled: { color: 'secondary', label: 'Cancelled' },
  }

  return map[status] ?? { color: 'secondary', label: status }
}

const planStateChip = state => {
  const map = {
    active: { color: 'success', label: 'Active' },
    paused: { color: 'secondary', label: 'Paused' },
    past_due: { color: 'error', label: 'Past due' },
    cancelled: { color: 'secondary', label: 'Cancelled' },
  }

  return map[state] ?? { color: 'secondary', label: state }
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/v1/client/services')

    orders.value = res.orders
    plans.value = res.recurring_plans
  } catch (err) {
    console.error('Failed to load services:', err)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const planWorking = ref(null)

const planAction = async (plan, action) => {
  planWorking.value = plan.id
  try {
    await $api(`/v1/client/recurring-plans/${plan.id}/${action}`, { method: 'POST' })
    await load()
  } catch (err) {
    console.error(`Plan ${action} failed:`, err)
  } finally {
    planWorking.value = null
  }
}
</script>

<template>
  <section>
    <VProgressCircular v-if="loading" indeterminate class="d-block mx-auto my-12" />

    <template v-else>
      <!-- Recurring services -->
      <VCard v-if="plans.length" class="mb-6" title="Recurring services">
        <VCardText>
          <VRow>
            <VCol
              v-for="plan in plans"
              :key="plan.id"
              cols="12"
              md="6"
              lg="4"
            >
              <VCard variant="outlined">
                <VCardText>
                  <div class="d-flex justify-space-between align-center mb-3">
                    <h6 class="text-h6">{{ plan.service_name }}</h6>
                    <VChip v-bind="planStateChip(plan.state)" size="small" label>
                      {{ planStateChip(plan.state).label }}
                    </VChip>
                  </div>
                  <div class="d-flex justify-space-between mb-2">
                    <span class="text-body-2">Price</span>
                    <span class="font-weight-medium">
                      {{ formatMoney(plan.amount_net) }} / {{ plan.interval === 'yearly' ? 'year' : 'month' }} net
                    </span>
                  </div>
                  <div class="d-flex justify-space-between mb-2">
                    <span class="text-body-2">Next charge</span>
                    <span class="font-weight-medium">{{ formatDate(plan.next_charge_at) }}</span>
                  </div>
                  <VAlert
                    v-if="!plan.has_payment_method && plan.state === 'active'"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mt-2"
                  >
                    No payment method on file —
                    <RouterLink :to="{ name: 'client-billing-payment-methods' }">add a card</RouterLink>
                    to avoid interruptions.
                  </VAlert>

                  <div v-if="['active', 'paused'].includes(plan.state)" class="d-flex gap-2 mt-3">
                    <VBtn
                      v-if="plan.state === 'active'"
                      size="small"
                      variant="tonal"
                      color="secondary"
                      :loading="planWorking === plan.id"
                      @click="planAction(plan, 'pause')"
                    >
                      Pause
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="error"
                      :loading="planWorking === plan.id"
                      @click="planAction(plan, 'cancel')"
                    >
                      Cancel
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Orders -->
      <VCard title="My services">
        <VCardText v-if="!orders.length">
          <VAlert type="info" variant="tonal">
            No services yet. Once the agency sets up an order for you, it
            shows up here.
          </VAlert>
        </VCardText>

        <template v-else>
          <VExpansionPanels variant="accordion">
            <VExpansionPanel v-for="order in orders" :key="order.id">
              <VExpansionPanelTitle>
                <div class="d-flex align-center justify-space-between flex-grow-1 me-4 gap-4">
                  <span class="font-weight-medium">{{ order.order_number }}</span>
                  <VChip v-bind="orderStatusChip(order.status)" size="small" label>
                    {{ orderStatusChip(order.status).label }}
                  </VChip>
                  <span class="text-body-2 d-none d-sm-block">{{ formatDate(order.created_at) }}</span>
                  <span class="font-weight-medium ms-auto">{{ formatMoney(order.total_gross) }}</span>
                </div>
              </VExpansionPanelTitle>
              <VExpansionPanelText>
                <VTable density="comfortable">
                  <thead>
                    <tr>
                      <th>Service</th>
                      <th class="text-end">Qty</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in order.items" :key="item.id">
                      <td>{{ item.description }}</td>
                      <td class="text-end">{{ item.quantity }}{{ item.unit ? ` ${item.unit}` : '' }}</td>
                      <td class="text-end">{{ formatMoney(item.line_total_gross) }}</td>
                    </tr>
                  </tbody>
                </VTable>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </template>
      </VCard>
    </template>
  </section>
</template>
