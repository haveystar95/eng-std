<script setup lang="ts">
// Thin ink progress bar on a track (design §4б). `value` is a 0..1 fraction.
import { computed } from 'vue'
const props = defineProps<{ value: number }>()
const pct = computed(() => Math.max(0, Math.min(1, props.value)) * 100)
</script>

<template>
  <div class="line" :aria-valuenow="Math.round(pct)" role="progressbar">
    <div class="fill" :style="{ width: `${pct}%` }" />
  </div>
</template>

<style scoped>
.line {
  height: 6px;
  background: var(--track);
  border-radius: 6px;
  overflow: hidden;
  min-width: 80px;
}
.fill {
  height: 100%;
  background: var(--ink);
  border-radius: 6px;
  min-width: 6px;
}
</style>
