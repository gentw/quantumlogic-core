<script setup>
import { useTheme } from 'vuetify'
import markColour from '@images/quantumlogic-mark.png'
import markWhite from '@images/quantumlogic-mark-white.png'

const { current } = useTheme()

/**
 * The brand mark is a single dark-purple glyph, so it vanishes against dark
 * surfaces. Swap to the white variant whenever the active Vuetify theme is dark.
 *
 * @type {import('vue').ComputedRef<string>}
 */
const src = computed(() => (current.value.dark ? markWhite : markColour))
</script>

<template>
  <img
    :src="src"
    class="app-logo-mark"
    alt="QuantumLogic"
  >
</template>

<style scoped>
.app-logo-mark {
  block-size: 1.75rem;
  inline-size: auto;
}
</style>
