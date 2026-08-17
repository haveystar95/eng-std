<script setup lang="ts">
/**
 * ⌘K / Ctrl+K: type a fragment, land on the card.
 *
 * Searches the three things that have cards — users by email, terms by text, collections by title —
 * in parallel, because the answer to "where is that thing" is usually one of them and you rarely
 * know which. Queries are debounced and every response carries its own sequence number, so a slow
 * early request can't overwrite the results of a later, narrower one.
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import type { RouteLocationRaw } from 'vue-router'

interface Hit {
  kind: 'user' | 'term' | 'collection'
  id: string
  title: string
  subtitle: string
  to: RouteLocationRaw
}

const KIND_LABEL: Record<Hit['kind'], string> = {
  user: 'юзер',
  term: 'термин',
  collection: 'коллекция',
}

const router = useRouter()
const open = ref(false)
const query = ref('')
const hits = ref<Hit[]>([])
const active = ref(0)
const searching = ref(false)
const input = ref<HTMLInputElement | null>(null)

let seq = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function search(text: string) {
  const term = text.trim()
  if (term.length < 2) {
    hits.value = []
    return
  }
  const mine = ++seq
  searching.value = true
  try {
    const [users, terms, collections] = await Promise.all([
      api.listUsers({ search: term, limit: 5 }),
      api.listTerms({ search: term, limit: 5 }),
      api.listCollections({ search: term, limit: 5 }),
    ])
    if (mine !== seq) return // a newer query already answered
    hits.value = [
      ...users.data.map((u): Hit => ({
        kind: 'user',
        id: u.id,
        title: u.email ?? u.name,
        subtitle: u.name,
        to: { name: 'user', params: { id: u.id, tab: 'plan' } },
      })),
      ...terms.data.map((t): Hit => ({
        kind: 'term',
        id: t.id,
        title: t.text,
        subtitle: t.translation ?? '',
        to: { name: 'term', params: { id: t.id } },
      })),
      ...collections.data.map((c): Hit => ({
        kind: 'collection',
        id: c.id,
        title: c.title,
        subtitle: c.ownerEmail ?? 'системная',
        to: { name: 'collection', params: { id: c.id } },
      })),
    ]
    active.value = 0
  } finally {
    if (mine === seq) searching.value = false
  }
}

watch(query, (text) => {
  clearTimeout(timer)
  timer = setTimeout(() => void search(text), 180)
})

function show() {
  open.value = true
  query.value = ''
  hits.value = []
  void nextTick(() => input.value?.focus())
}
function hide() {
  open.value = false
}
function go(hit: Hit) {
  hide()
  router.push(hit.to)
}

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    open.value ? hide() : show()
    return
  }
  if (!open.value) return
  if (e.key === 'Escape') hide()
  else if (e.key === 'ArrowDown') {
    e.preventDefault()
    active.value = Math.min(active.value + 1, hits.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    active.value = Math.max(active.value - 1, 0)
  } else if (e.key === 'Enter' && hits.value[active.value]) {
    e.preventDefault()
    go(hits.value[active.value])
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(timer)
})

defineExpose({ show })
</script>

<template>
  <div v-if="open" class="cp-backdrop" @click.self="hide">
    <div class="cp" role="dialog" aria-label="Поиск">
      <input
        ref="input"
        v-model="query"
        type="text"
        class="cp-input"
        placeholder="Юзер по email, термин, коллекция…"
      />
      <p v-if="query.trim().length < 2" class="cp-hint">Введите хотя бы два символа.</p>
      <p v-else-if="searching && hits.length === 0" class="cp-hint">Ищем…</p>
      <p v-else-if="hits.length === 0" class="cp-hint">Ничего не нашлось.</p>
      <ul v-else class="cp-list">
        <li
          v-for="(hit, i) in hits"
          :key="hit.kind + hit.id"
          :class="{ active: i === active }"
          @mouseenter="active = i"
          @click="go(hit)"
        >
          <span class="cp-kind">{{ KIND_LABEL[hit.kind] }}</span>
          <span class="cp-title">{{ hit.title }}</span>
          <span class="cp-sub">{{ hit.subtitle }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.cp-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 12vh;
  z-index: 100;
}
.cp {
  width: min(560px, 92vw);
  background: var(--paper, #fff);
  border-radius: var(--r-card, 12px);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}
.cp-input {
  width: 100%;
  border: none;
  border-bottom: 1px solid var(--hairline);
  padding: 14px 18px;
  font-size: 16px;
  font-family: inherit;
  background: transparent;
  outline: none;
}
.cp-hint {
  margin: 0;
  padding: 14px 18px;
  font-size: 13px;
  color: var(--secondary);
}
.cp-list {
  list-style: none;
  margin: 0;
  padding: 6px;
  max-height: 52vh;
  overflow-y: auto;
}
.cp-list li {
  display: flex;
  align-items: baseline;
  gap: var(--s12);
  padding: 9px 12px;
  border-radius: var(--r-field);
  cursor: pointer;
}
.cp-list li.active {
  background: var(--faint-ink);
}
.cp-kind {
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 700;
  color: var(--secondary);
  min-width: 76px;
}
.cp-title {
  font-weight: 600;
  font-size: 14px;
}
.cp-sub {
  font-size: 12.5px;
  color: var(--secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
