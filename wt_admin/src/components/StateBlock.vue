<script setup lang="ts">
// Unified empty / error / loading state in the paper style. Keeps every page's
// non-data states visually consistent (design quality rule).
import PaperButton from './PaperButton.vue'
withDefaults(
  defineProps<{
    kind: 'loading' | 'empty' | 'error'
    title?: string
    message?: string
    retryable?: boolean
  }>(),
  { retryable: false },
)
defineEmits<{ retry: [] }>()
</script>

<template>
  <div class="state" :class="kind">
    <div v-if="kind === 'loading'" class="spinner" aria-label="Загрузка" />
    <div v-else class="glyph" aria-hidden="true">
      {{ kind === 'error' ? '⚠' : '∅' }}
    </div>
    <div class="title">
      {{ title ?? (kind === 'loading' ? 'Загрузка…' : kind === 'error' ? 'Не удалось загрузить' : 'Пусто') }}
    </div>
    <div v-if="message" class="msg faint">{{ message }}</div>
    <PaperButton
      v-if="kind === 'error' && retryable"
      variant="quiet"
      small
      class="retry"
      @click="$emit('retry')"
    >
      Повторить
    </PaperButton>
  </div>
</template>

<style scoped>
.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--s8);
  padding: 48px 24px;
  text-align: center;
  color: var(--secondary);
}
.glyph {
  font-size: 34px;
  color: var(--tertiary);
  line-height: 1;
}
.state.error .glyph {
  color: var(--destructive);
}
.title {
  font-weight: 700;
  color: var(--ink);
}
.msg {
  font-size: 13px;
  max-width: 42ch;
}
.retry {
  margin-top: var(--s4);
}
.spinner {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 2.5px solid var(--track);
  border-top-color: var(--ink);
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
