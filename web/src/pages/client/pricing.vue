<script setup>
import { useToast } from 'vue-toast-notification'
import { useTrialFingerprint } from '@/composables/useTrialFingerprint'

const router = useRouter()
const $toast = useToast()
const { get: getTrialFingerprint } = useTrialFingerprint()

const props = defineProps({
  title: {
    type: String,
    required: false,
  },
  xs: Number,
  sm: Number,
  md: [String, Number],
  lg: [String, Number],
  xl: [String, Number],
})

const generateTrialInvoice = async () => {
  try {
    const res = await $api('/v1/client/sub/generateTrialInvoice', {
      method: 'POST',
      headers: {
        'X-Trial-Fingerprint': getTrialFingerprint(),
      },
    })

    const invoiceId = res.invoice
    if (!invoiceId) {
      $toast.error('Invoice ID not returned from API.')
      return
    }

    $toast.success('Invoice was created successfully!')
    router.push(`/client/invoice/pay-now/${invoiceId}`)
  } catch (err) {
    if (err?.response?._data?.trial_blocked) {
      $toast.error(err.response._data.message ?? 'Trial already used.')
      return
    }
    $toast.error('Failed to create trial invoice.')
  }
}

const generateInvoice = async planId => {
  try {
    const res = await $api('/v1/client/sub/generateInvoice', {
      method: 'POST',
      headers: {
        'X-Trial-Fingerprint': getTrialFingerprint(),
      },
      body: {
        is_annual: annualMonthlyPlanPriceToggler.value,
        plan_id: planId,
      },
    })

    const invoiceId = res.invoice
    if (!invoiceId) {
      $toast.error('Invoice ID not returned from API.')
      return
    }

    $toast.success('Invoice was created successfully!')
    router.push(`/client/invoice/pay-now/${invoiceId}`)
  } catch (err) {
    $toast.error('Failed to create invoice.')
  }
}

const annualMonthlyPlanPriceToggler = ref(true)

const pricingPlans = [
  {
    id: 1,
    name: 'Starter',
    tagLine: 'Essential protection & performance for small sites',
    monthlyPrice: 14,
    yearlyPrice: 149,
    isPopular: true,
    current: false,
    features: [
      '7-Day Free Trial',
      '1 Origin IP',
      'Load Balancing: Simple (1 origin only)',
      'TLS Mode: Full',
      'AI Logs: 7 days',
      'Basic Optimization (Images, Minify CSS/JS)',
      'Client Agent: Basic server optimization',
      'Bot Protection: Standard',
      'Email Support',
    ],
  },
  {
    id: 2,
    name: 'Professional',
    tagLine: 'Security & performance for growing businesses',
    monthlyPrice: 34,
    yearlyPrice: 349,
    isPopular: true,
    current: false,
    features: [
      'Up to 3 Origin IPs',
      'Load Balancing: Round-Robin + Failover',
      'TLS Mode: Full Strict',
      'AI Logs: 30 days with Alerts',
      'Cache + Performance Optimization',
      'Client Agent: Advanced optimization & monitoring',
      'Bot Protection: Moderate (AI-based blocking)',
      'Priority Email Support',
    ],
  },
  {
    id: 3,
    name: 'Growth',
    tagLine: 'Advanced protection & multi-framework support',
    monthlyPrice: 69,
    yearlyPrice: 699,
    isPopular: false,
    current: false,
    features: [
      'Up to 5 Origin IPs',
      'Advanced Load Balancing (Weighted + Health Checks)',
      'TLS Mode: Full Strict',
      'AI Logs: 90 days + filtering & email alerts',
      'Full Optimization Suite',
      'Client Agent: Full control & analytics',
      'Bot Protection: Advanced (Behavior-based + custom rules)',
      'Phone + Priority Support',
    ],
  },
]
</script>

<template>
  <div class="text-center">
    <h3 class="text-h3 pricing-title mb-2">
      {{ props.title ? props.title : 'SentriGate Pricing Plans' }}
    </h3>
    <p class="mb-0">
      All plans include AI security monitoring, optimization, and multi-framework agents.
    </p>
    <p class="mb-2">
      Choose the best plan for your site or SaaS platform.
    </p>
  </div>

  <!-- Annual/Monthly Toggle -->
  <div class="d-flex font-weight-medium text-body-1 align-center justify-center mx-auto mt-12 mb-6">
    <VLabel for="pricing-plan-toggle" class="me-3">Monthly</VLabel>
    <div class="position-relative">
      <VSwitch id="pricing-plan-toggle" v-model="annualMonthlyPlanPriceToggler">
        <template #label>
          <div class="text-body-1 font-weight-medium">Annually</div>
        </template>
      </VSwitch>
      <div class="save-upto-chip position-absolute align-center d-none d-md-flex gap-1">
        <VIcon icon="tabler-corner-left-down" size="24" class="flip-in-rtl mt-2 text-disabled" />
        <VChip label color="primary" size="small">Save up to 10%</VChip>
      </div>
    </div>
  </div>

  <!-- Pricing Cards -->
  <VRow>
    <VCol
      v-for="plan in pricingPlans"
      :key="plan.name"
      v-bind="props"
      cols="4"
    >
      <VCard flat border :class="plan.isPopular ? 'border-primary border-opacity-100' : ''">
        <VCardText style="block-size: 3.75rem;" class="text-end">
          <VChip v-show="plan.isPopular" label color="primary" size="small">Popular</VChip>
        </VCardText>

        <VCardText>
          <h4 class="text-h4 mb-1 text-center">{{ plan.name }}</h4>
          <p class="mb-0 text-body-1 text-center">{{ plan.tagLine }}</p>

          <div class="position-relative">
            <div class="d-flex justify-center pt-5 pb-10">
              <div class="text-body-1 align-self-start font-weight-medium">$</div>
              <h1 class="text-h1 font-weight-medium text-primary">
                {{ annualMonthlyPlanPriceToggler ? Math.floor(Number(plan.yearlyPrice) / 12) : plan.monthlyPrice }}
              </h1>
              <div class="text-body-1 font-weight-medium align-self-end">/month</div>
            </div>
            <span v-show="annualMonthlyPlanPriceToggler" class="annual-price-text position-absolute text-caption text-disabled pb-4">
              {{ plan.yearlyPrice === 0 ? 'Free' : `USD ${plan.yearlyPrice}/Year` }}
            </span>
          </div>

          <VList class="card-list mb-4">
            <VListItem v-for="feature in plan.features" :key="feature">
              <template #prepend>
                <VIcon
                  size="8"
                  icon="tabler-circle-filled"
                  color="rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity))"
                />
              </template>
              <VListItemTitle class="text-body-1">{{ feature }}</VListItemTitle>
            </VListItem>
          </VList>

          <VBtn
            v-if="plan.id === 1"
            block
            :color="plan.current ? 'success' : 'primary'"
            variant="tonal"
            @click="generateTrialInvoice"
          >
            Start 7-Day Free Trial
          </VBtn>

          <VBtn
            block
            :color="plan.current ? 'success' : 'primary'"
            :variant="plan.isPopular ? 'elevated' : 'tonal'"
            class="mt-2"
            @click="generateInvoice(plan.id)"
          >
            Upgrade
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1rem;
}
.save-upto-chip {
  inset-block-start: -2.4rem;
  inset-inline-end: -6rem;
}
.annual-price-text {
  inset-block-end: 3%;
  inset-inline-start: 50%;
  transform: translateX(-50%);
}
</style>
