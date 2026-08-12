<script setup lang="ts">
// Scroll sentinel + the states a growing list has: first load, empty, error, loading more,
// and "that was everything". Wraps any table so no view re-implements the observer.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import StateBlock from './StateBlock.vue'

const props = defineProps<{
  loading: boolean
  loadingMore: boolean
  error: string | null
  done: boolean
  count: number
  total?: number
  emptyMessage?: string
}>()
const emit = defineEmits<{ more: []; retry: [] }>()

const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

function observe() {
  if (!sentinel.value || typeof IntersectionObserver === 'undefined') return
  observer?.disconnect()
  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) emit('more')
    },
    // Ask early: the next page should be arriving while the last rows are still on screen.
    { rootMargin: '400px' },
  )
  observer.observe(sentinel.value)
}

onMounted(observe)
watch(() => props.done, (isDone) => { if (isDone) observer?.disconnect() })
onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div>
    <StateBlock v-if="loading && count === 0" kind="loading" />
    <StateBlock
      v-else-if="error && count === 0"
      kind="error"
      :message="error"
      retryable
      @retry="emit('retry')"
    />
    <StateBlock v-else-if="count === 0" kind="empty" :message="emptyMessage ?? 'Ничего не найдено'" />

    <template v-else>
      <slot />

      <div ref="sentinel" class="sentinel">
        <span v-if="loadingMore" class="hint">Загружаем ещё…</span>
        <span v-else-if="error" class="hint err">{{ error }}</span>
        <span v-else-if="done" class="hint">
          Всё — {{ total ?? count }}
        </span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.sentinel {
  display: flex;
  justify-content: center;
  padding: var(--s16) 0;
  min-height: 32px;
}
.hint {
  font-size: 12px;
  color: var(--secondary);
}
.hint.err {
  color: var(--destructive, #b4423a);
}
</style>
