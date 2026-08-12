<script setup>
definePage({
  meta: { layout: 'blank', public: true },
})

const route = useRoute('order-success')
const orderNumber = computed(() => route.query.order ?? '')
</script>

<template>
  <VContainer class="py-16 text-center" style="max-inline-size: 36rem;">
    <div class="d-flex justify-center mb-8">
      <AppLogo />
    </div>

    <VIcon icon="tabler-circle-check" size="64" color="success" class="mb-4" />
    <h4 class="text-h4 mb-2">{{ $t('order.thankYou') }}</h4>
    <p class="text-body-1 mb-6">
      <template v-if="orderNumber">Order {{ orderNumber }} is confirmed.</template>
      {{ $t('order.successBody') }}
    </p>

    <VBtn color="primary" :to="{ name: 'login' }">
      {{ $t('order.goToLogin') }}
    </VBtn>
  </VContainer>
</template>
