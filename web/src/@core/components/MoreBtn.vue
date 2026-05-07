<script setup>
const props = defineProps({
  menuList: {
    type: Array,
    required: false,
  },
  itemProps: {
    type: Boolean,
    required: false,
  },
})
</script>

<template>
  <IconBtn color="disabled">
    <VIcon icon="tabler-dots" />

    <VMenu
      v-if="props.menuList"
      activator="parent"
    >
      <VList>
        <VListItem
          v-for="menuItem in props.menuList"
          :key="menuItem.id"
          @click="$emit('click', menuItem)"
          :value="menuItem"
        >
          <slot name="item" :menuItem="menuItem">
            <span>{{ menuItem.title }}</span>
          </slot>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
