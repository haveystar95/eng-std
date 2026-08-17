<script setup lang="ts">
// Page title row with an optional back link and a right-aligned actions slot.
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
defineProps<{ title: string; subtitle?: string; back?: { to: RouteLocationRaw; label: string } }>()
</script>

<template>
  <header class="ph">
    <div class="left">
      <RouterLink v-if="back" :to="back.to" class="back">← {{ back.label }}</RouterLink>
      <h1 class="title">{{ title }}</h1>
      <p v-if="subtitle" class="subtitle muted">{{ subtitle }}</p>
    </div>
    <div class="actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.ph {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--s16);
  margin-bottom: var(--s22);
  flex-wrap: wrap;
}
.back {
  display: inline-block;
  font-size: 13px;
  font-weight: 600;
  color: var(--secondary);
  margin-bottom: var(--s8);
}
.back:hover {
  color: var(--ink);
}
.title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.subtitle {
  margin: 4px 0 0;
  font-size: 14px;
}
.actions {
  display: flex;
  align-items: center;
  gap: var(--s12);
  flex-wrap: wrap;
}
</style>
