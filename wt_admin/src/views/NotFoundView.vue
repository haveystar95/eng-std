<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="nf">
    <div class="code serif">404</div>
    <p class="muted">Страница не найдена.</p>
    <RouterLink :to="{ name: 'dashboard' }" class="link">← На обзор</RouterLink>
  </div>
</template>

<style scoped>
.nf {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--s12);
}
.code {
  font-family: var(--font-serif);
  font-size: 72px;
  font-weight: 500;
  color: var(--ink);
}
.link {
  color: var(--ink);
  font-weight: 600;
  border-bottom: 1px solid var(--track);
}
</style>
