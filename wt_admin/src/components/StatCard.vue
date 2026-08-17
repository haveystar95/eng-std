<script setup lang="ts">
// A single number on a paper card. No chart libraries in v1 — figures as cards.
import PaperCard from './PaperCard.vue'
defineProps<{ label: string; value: string; hint?: string; serif?: boolean }>()
</script>

<template>
  <PaperCard class="stat">
    <div class="section-label">{{ label }}</div>
    <div class="value tnum" :class="{ serif }">{{ value }}</div>
    <div v-if="hint" class="hint faint">{{ hint }}</div>
  </PaperCard>
</template>

<style scoped>
.stat {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.value {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.05;
}
.value.serif {
  font-family: var(--font-serif);
  font-weight: 500;
}
.hint {
  font-size: 12px;
}
</style>
