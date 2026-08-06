<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])


const file = ref(null)
const formValid = ref(false)

const closeDialog = () => {
  emit('update:modelValue', false)
  file.value = null
}

const submitVerification = () => {
  if (!file.value) return

  emit('submit', {
    file: file.value,
  })

  closeDialog()
}

</script>


<template>
  <VDialog
     :model-value="modelValue"
    width="500"
    @update:model-value="emit('update:modelValue', $event)"
  >
  

    <!-- Dialog close btn -->
    <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

    <VCard>
      <!-- Header -->
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-file-check" />
        Verify Payment (Wise / Bank Transfer)
      </VCardTitle>

      <VDivider />

      <!-- Content -->
      <VCardText class="pt-4">
        <p class="text-body-2 mb-4">
          If you have completed the payment via <strong>Wise or bank transfer</strong>,
          please upload a document that confirms the transaction.
          <br /><br />
          Accepted documents:
          <strong>payment confirmation, transfer receipt, or bank screenshot</strong>.
        </p>

        <VFileInput
          v-model="file"
          label="Upload payment confirmation"
          prepend-icon="tabler-upload"
          accept=".pdf,image/png,image/jpeg"
          show-size
          clearable
          required
        />
      </VCardText>

      <VDivider />

      <!-- Actions -->
      <VCardActions class="justify-end mt-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
        >
          Cancel
        </VBtn>

        <VBtn
          variant="tonal"
          @click="submitVerification"
          :disabled="!file"
        >
          Submit Verification
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
