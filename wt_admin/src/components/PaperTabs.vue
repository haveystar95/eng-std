<script setup lang="ts">
// Pill tab bar (design §3). Controlled by v-model of the active tab key.
defineProps<{ tabs: { key: string; label: string }[]; modelValue: string }>()
defineEmits<{ 'update:modelValue': [key: string] }>()
</script>

<template>
  <div class="tabs">
    <button
      v-for="t in tabs"
      :key="t.key"
      class="tab"
      :class="{ active: t.key === modelValue }"
      @click="$emit('update:modelValue', t.key)"
    >
      {{ t.label }}
    </button>
  </div>
</template>

<style scoped>
.tabs {
  display: inline-flex;
  gap: 2px;
  padding: 4px;
  background: var(--faint-ink);
  border-radius: var(--r-pill);
  flex-wrap: wrap;
}
.tab {
  border: none;
  background: transparent;
  border-radius: var(--r-pill);
  padding: 8px 16px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--secondary);
  transition: background 0.12s ease, color 0.12s ease;
}
.tab:hover {
  color: var(--ink);
}
.tab.active {
  background: var(--surface-raised);
  color: var(--ink);
  font-weight: 700;
  box-shadow: var(--shadow-card);
}
</style>
