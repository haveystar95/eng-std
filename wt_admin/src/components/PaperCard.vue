<script setup lang="ts">
// Layered paper surface — no border, warm double shadow (design rule 13).
withDefaults(defineProps<{ pad?: boolean; as?: string }>(), { pad: true, as: 'div' })
</script>

<template>
  <component :is="as" class="paper-card" :class="{ pad }">
    <slot />
  </component>
</template>

<style scoped>
.paper-card {
  background: var(--surface-raised);
  border-radius: var(--r-card);
  box-shadow: var(--shadow-card);
}
.paper-card.pad {
  padding: var(--s16);
}
</style>
