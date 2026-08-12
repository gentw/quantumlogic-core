<script setup>
/**
 * The client dashboard's billing body: the invoice that needs paying next, a
 * KPI strip, recent invoices and active services.
 *
 * Extracted from pages/client/index.vue, which is still Options API and carries
 * the chat plumbing — this keeps the new work in <script setup> without
 * rewriting that file wholesale.
 */
const router = useRouter()
const billingApi = useBillingApi()

const summary = ref(null)
const invoices = ref([])
const plans = ref([])
const orders = ref([])
const loading = ref(true)

/** The invoice the client has to pay next, or null when nothing is open. */
const nextDue = computed(() => summary.value?.next_due ?? null)

/** Recurring plans still costing the client money. */
const activePlans = computed(() => plans.value.filter(plan => ['active', 'past_due'].includes(plan.state)))

/** Orders the agency is still delivering — draft orders never reach the client. */
const openOrders = computed(() =>
  orders.value.filter(order => ['awaiting_payment', 'active', 'in_delivery'].includes(order.status)))

const load = async () => {
  loading.value = true

  const [summaryRes, invoicesRes, servicesRes] = await Promise.allSettled([
    billingApi.summary(),
    billingApi.invoices({ perPage: 5 }),
    billingApi.services(),
  ])

  if (summaryRes.status === 'fulfilled') summary.value = summaryRes.value
  if (invoicesRes.status === 'fulfilled') invoices.value = invoicesRes.value.data ?? []

  if (servicesRes.status === 'fulfilled') {
    plans.value = servicesRes.value.recurring_plans ?? []
    orders.value = servicesRes.value.orders ?? []
  }

  loading.value = false
}

const payNextDue = () => {
  if (nextDue.value) router.push({ name: 'client-billing-checkout-id', params: { id: nextDue.value.id } })
}

const openInvoice = id => router.push({ name: 'client-billing-invoices-id', params: { id } })

onMounted(load)
</script>

<template>
  <VRow>
    <!-- 👉 Next invoice due -->
    <VCol cols="12" md="5">
      <VCard class="h-100">
        <VCardItem>
          <VCardTitle>{{ $t('dashboard.nextInvoiceDue') }}</VCardTitle>
        </VCardItem>

        <VCardText v-if="loading">
          <VSkeletonLoader type="list-item-two-line, actions" />
        </VCardText>

        <VCardText v-else-if="nextDue">
          <div class="d-flex align-center gap-2 mb-2">
            <h4 class="text-h4">
              {{ formatMoney(nextDue.amount_due) }}
            </h4>
            <VChip
              :color="nextDue.is_overdue ? 'error' : 'info'"
              size="small"
              label
            >
              {{ dueLabel(nextDue.due_at) }}
            </VChip>
          </div>

          <div class="text-body-2 text-medium-emphasis mb-1">
            {{ $t('dashboard.invoiceNumber', { number: nextDue.invoice_number }) }}
          </div>
          <div class="text-body-2 text-medium-emphasis mb-4">
            {{ $t('dashboard.dueTotal', { date: formatDate(nextDue.due_at), total: formatMoney(nextDue.total_gross) }) }}
          </div>

          <div class="d-flex flex-wrap gap-3">
            <VBtn @click="payNextDue">
              {{ $t('dashboard.payNow') }}
              <VIcon icon="tabler-arrow-right" end class="flip-in-rtl" />
            </VBtn>
            <VBtn variant="tonal" color="secondary" @click="openInvoice(nextDue.id)">
              {{ $t('dashboard.viewInvoice') }}
            </VBtn>
          </div>
        </VCardText>

        <VCardText v-else class="text-center py-8">
          <VAvatar color="success" variant="tonal" size="52" class="mb-3">
            <VIcon icon="tabler-check" size="28" />
          </VAvatar>
          <h6 class="text-h6 mb-1">
            {{ $t('dashboard.allCaughtUp') }}
          </h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('dashboard.nothingToPay') }}
          </p>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 KPI strip -->
    <VCol cols="12" md="7">
      <VCard class="h-100">
        <VCardText class="h-100 d-flex align-center">
          <VRow>
            <VCol cols="12" sm="4">
              <div class="d-flex align-center gap-3">
                <VAvatar variant="tonal" color="primary" rounded size="42">
                  <VIcon icon="tabler-credit-card" size="24" />
                </VAvatar>
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ $t('dashboard.outstanding') }}
                  </div>
                  <h5 class="text-h5">
                    <VSkeletonLoader v-if="loading" type="text" width="90" />
                    <template v-else>
                      {{ formatMoney(summary?.outstanding_balance) }}
                    </template>
                  </h5>
                </div>
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div class="d-flex align-center gap-3">
                <VAvatar variant="tonal" color="warning" rounded size="42">
                  <VIcon icon="tabler-file-invoice" size="24" />
                </VAvatar>
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ $t('dashboard.openInvoices') }}
                  </div>
                  <h5 class="text-h5">
                    <VSkeletonLoader v-if="loading" type="text" width="40" />
                    <template v-else>
                      {{ summary?.open_invoices ?? 0 }}
                    </template>
                  </h5>
                </div>
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div class="d-flex align-center gap-3">
                <VAvatar variant="tonal" color="success" rounded size="42">
                  <VIcon icon="tabler-briefcase" size="24" />
                </VAvatar>
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ $t('dashboard.activeServices') }}
                  </div>
                  <h5 class="text-h5">
                    <VSkeletonLoader v-if="loading" type="text" width="40" />
                    <template v-else>
                      {{ activePlans.length + openOrders.length }}
                    </template>
                  </h5>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Recent invoices -->
    <VCol cols="12" md="7">
      <VCard>
        <VCardItem>
          <VCardTitle>{{ $t('dashboard.recentInvoices') }}</VCardTitle>
          <template #append>
            <VBtn variant="text" size="small" :to="{ name: 'client-billing' }">
              {{ $t('common.viewAll') }}
            </VBtn>
          </template>
        </VCardItem>

        <VCardText v-if="loading">
          <VSkeletonLoader type="table-row@3" />
        </VCardText>

        <VList v-else-if="invoices.length" lines="two">
          <VListItem
            v-for="invoice in invoices"
            :key="invoice.id"
            link
            @click="openInvoice(invoice.id)"
          >
            <VListItemTitle class="font-weight-medium">
              {{ invoice.invoice_number }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ invoiceTypeLabel(invoice.type) }} · {{ formatDate(invoice.due_at) }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center gap-3">
                <span class="font-weight-medium">{{ formatMoney(invoice.total_gross) }}</span>
                <VChip
                  :color="invoiceStatusChip(invoice).color"
                  size="small"
                  label
                >
                  {{ invoiceStatusChip(invoice).label }}
                </VChip>
              </div>
            </template>
          </VListItem>
        </VList>

        <VCardText v-else class="text-medium-emphasis">
          {{ $t('dashboard.noInvoices') }}
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 My services -->
    <VCol cols="12" md="5">
      <VCard>
        <VCardItem>
          <VCardTitle>{{ $t('dashboard.myServices') }}</VCardTitle>
          <template #append>
            <VBtn variant="text" size="small" :to="{ name: 'client-services' }">
              {{ $t('common.viewAll') }}
            </VBtn>
          </template>
        </VCardItem>

        <VCardText v-if="loading">
          <VSkeletonLoader type="list-item-two-line@2" />
        </VCardText>

        <VList v-else-if="activePlans.length || openOrders.length" lines="two">
          <VListItem
            v-for="plan in activePlans"
            :key="`plan-${plan.id}`"
          >
            <VListItemTitle class="font-weight-medium">
              {{ plan.service_name ?? 'Recurring service' }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ $t('dashboard.renews') }} {{ formatDate(plan.next_charge_at) }} · {{ plan.interval === 'yearly' ? $t('dashboard.yearly') : $t('dashboard.monthly') }}
            </VListItemSubtitle>

            <template #append>
              <VChip
                :color="plan.state === 'past_due' ? 'error' : 'success'"
                size="small"
                label
              >
                {{ plan.state === 'past_due' ? 'Past due' : 'Active' }}
              </VChip>
            </template>
          </VListItem>

          <VListItem
            v-for="order in openOrders"
            :key="`order-${order.id}`"
          >
            <VListItemTitle class="font-weight-medium">
              {{ order.order_number }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ formatMoney(order.total_gross) }} · {{ $t('dashboard.ordered') }} {{ formatDate(order.created_at) }}
            </VListItemSubtitle>

            <template #append>
              <VChip color="info" size="small" label>
                {{ order.status.replace('_', ' ') }}
              </VChip>
            </template>
          </VListItem>
        </VList>

        <VCardText v-else class="text-medium-emphasis">
          {{ $t('dashboard.noServices') }}
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
