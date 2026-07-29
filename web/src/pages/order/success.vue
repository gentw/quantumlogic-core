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
    <h4 class="text-h4 mb-2">Thank you!</h4>
    <p class="text-body-1 mb-6">
      <template v-if="orderNumber">Order {{ orderNumber }} is confirmed.</template>
      Your deposit is being processed — you'll receive a receipt and a link to
      set your account password by email. Work starts as soon as the payment
      settles.
    </p>

    <VBtn color="primary" :to="{ name: 'login' }">
      Go to login
    </VBtn>
  </VContainer>
</template>
