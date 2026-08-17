<script setup lang="ts">
// Scroll sentinel + the states a growing list has: first load, empty, error, loading more,
// and "that was everything". Wraps any table so no view re-implements the loading dance.
import { onBeforeUnmount, ref, watch } from 'vue'
import StateBlock from './StateBlock.vue'

/** How far below the fold the sentinel may still be and already trigger the next page. */
const LOOKAHEAD_PX = 400

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
let listening = false
let scheduled = false
let frame = 0

/**
 * A plain scroll listener, deliberately NOT an IntersectionObserver.
 *
 * The observer version shipped broken in two independent ways, and both failed SILENTLY — the list
 * just stopped growing, which is indistinguishable from "there is no more data":
 *   1. it was armed in `onMounted`, when the sentinel is not rendered yet (the list is still in its
 *      loading state), so it attached to nothing;
 *   2. even armed correctly, the callback never fired in an embedded/headless browser.
 * A geometry check on scroll has no such failure modes, needs no feature detection, and is
 * trivially testable. The visible "Загрузить ещё" button below is the third line of defence.
 */
function check() {
  if (props.done || props.loading || props.loadingMore || !sentinel.value) return
  const top = sentinel.value.getBoundingClientRect().top
  if (top <= window.innerHeight + LOOKAHEAD_PX) emit('more')
}

// The re-entry guard is a flag set BEFORE scheduling and cleared INSIDE the frame — not the frame
// id, which is only assigned after the callback has already run when frames resolve synchronously,
// leaving the guard stuck on and every later scroll ignored.
function schedule() {
  if (scheduled) return
  scheduled = true
  frame = requestAnimationFrame(() => {
    scheduled = false
    check()
  })
}

function listen(on: boolean) {
  if (on === listening) return
  listening = on
  if (on) {
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', schedule)
  } else {
    window.removeEventListener('scroll', schedule)
    window.removeEventListener('resize', schedule)
  }
}

watch(
  [sentinel, () => props.done, () => props.count],
  ([el, isDone]) => {
    listen(Boolean(el) && !isDone)
    // Also check right after each render: on a tall screen the first page may not fill the
    // viewport, and then there is no scroll event to wait for.
    if (el && !isDone) schedule()
  },
  { immediate: true, flush: 'post' },
)

onBeforeUnmount(() => {
  listen(false)
  if (frame) cancelAnimationFrame(frame)
})
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
        <span v-else-if="error" class="hint err">
          {{ error }}
          <button class="more" @click="emit('more')">Повторить</button>
        </span>
        <span v-else-if="done" class="hint">Всё — {{ total ?? count }}</span>
        <!--
          Автоподгрузка по скроллу — основной путь, но кнопка тут не для красоты: она делает
          состояние «есть ещё» видимым и оставляет список рабочим, если наблюдатель не сработал
          (короткая страница, зум, нестандартный контейнер).
        -->
        <button v-else class="more" @click="emit('more')">
          Загрузить ещё<span v-if="total"> · показано {{ count }} из {{ total }}</span>
        </button>
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
.more {
  border: 1px solid var(--hairline);
  background: var(--field);
  border-radius: var(--r-pill, 999px);
  padding: 7px 16px;
  font-size: 12.5px;
  font-family: inherit;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.more:hover {
  color: var(--ink);
}
.hint .more {
  margin-left: 8px;
}
</style>
