<script setup lang="ts">
// Where am I, and what is one level up. Cheap orientation that matters most on a deep link:
// arriving straight at /terms/:id/… from a shared URL, this is the only thing on the page that
// says what section you are in.
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'

export interface Crumb {
  label: string
  to?: RouteLocationRaw
}

defineProps<{ items: Crumb[] }>()
</script>

<template>
  <nav class="crumbs" aria-label="Хлебные крошки">
    <template v-for="(item, i) in items" :key="i">
      <span v-if="i > 0" class="sep" aria-hidden="true">→</span>
      <RouterLink v-if="item.to" :to="item.to" class="crumb">{{ item.label }}</RouterLink>
      <span v-else class="crumb current" aria-current="page">{{ item.label }}</span>
    </template>
  </nav>
</template>

<style scoped>
.crumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: var(--s8, 8px);
  font-size: 12px;
  color: var(--secondary);
}
.crumb {
  color: var(--secondary);
  text-decoration: none;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.crumb:hover:not(.current) {
  color: var(--ink, #1a1a1a);
  text-decoration: underline;
}
.crumb.current {
  color: var(--ink, #1a1a1a);
}
.sep {
  opacity: 0.5;
}
</style>
