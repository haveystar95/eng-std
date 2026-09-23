<script setup lang="ts">
// «Вызовы API» (ADM-1): one journal of the plan — model calls (by the build and talk windows), voice
// purchases, slot judges and the client's calls to the plan's routes — newest first, a page at a
// time. Filter by source (the page's day filter applies too); lost calls and errors stand out; a row
// opens its JSON, a model call its request and answer.
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import type { CallSource, PlanCallRow, PlanCalls } from '@/api/planTypes'
import JsonBlock from '@/components/JsonBlock.vue'
import PaperButton from '@/components/PaperButton.vue'
import StatusChip from '@/components/StatusChip.vue'
import { absoluteTime } from '@/utils/format'
import { duration, tokensBill, voiceBill, SOURCE_LABEL } from '@/utils/planLabels'
import CallDetail from './CallDetail.vue'
import PlanBlock from './PlanBlock.vue'
import { jsonName } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const PAGE = 50

const source = ref<CallSource | null>(null)
const rows = ref<PlanCallRow[]>([])
const meta = ref<PlanCalls['meta'] | null>(null)
const loading = ref(false)
const loadingMore = ref(false)
const error = ref<string | null>(null)
const open = ref<string | null>(null)
const raw = computed(() => (meta.value === null ? null : { data: rows.value, meta: meta.value }))

async function load(more = false) {
  const cursor = more ? meta.value?.next_cursor ?? null : null
  ;(more ? loadingMore : loading).value = true
  error.value = null
  try {
    const page = await api.getPlanCalls(props.code, { day: props.day, source: source.value, cursor, limit: PAGE })
    rows.value = more ? [...rows.value, ...page.data] : page.data
    meta.value = page.meta
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

onMounted(() => void load())
watch([() => props.code, () => props.day, source], () => void load())

function bill(r: PlanCallRow): string {
  if (r.source === 'voice') return voiceBill(r.credits ?? null, r.characters ?? null, r.cost_usd ?? null)
  if (r.source === 'client') return `${r.response_bytes ?? '—'} байт`
  return tokensBill(r.tokens_in ?? null, r.tokens_out ?? null, r.cost_usd ?? null)
}
</script>

<template>
  <PlanBlock
    id="calls"
    title="Вызовы API"
    subtitle="Модель, голос, судья слотов и обращения клиента — единый журнал, постранично"
    :raw="raw"
    :filename="jsonName(code, 'calls', day)"
    :loading="loading"
    :error="error"
    @retry="load()"
  >
    <template #actions>
      <span class="filters">
        <button class="pill" :class="{ on: source === null }" @click="source = null">все</button>
        <button v-for="(label, key) in SOURCE_LABEL" :key="key" class="pill" :class="{ on: source === key }" @click="source = key as CallSource">{{ label }}</button>
      </span>
    </template>
    <template v-if="meta">
      <p class="faint count tnum">Показано {{ rows.length }} из {{ meta.total }}</p>
      <table class="jt">
        <thead>
          <tr>
            <th>Время</th>
            <th>Источник</th>
            <th>День</th>
            <th>Что</th>
            <th>Статус</th>
            <th>Длительность</th>
            <th>Счёт</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="r in rows" :key="`${r.source}-${r.id}`">
            <tr :class="{ bad: r.is_error }">
              <td class="tnum">{{ absoluteTime(r.at) }}</td>
              <td>{{ SOURCE_LABEL[r.source] ?? r.source }}</td>
              <td class="tnum">{{ r.day ?? '—' }}</td>
              <td class="what">{{ r.stage }}<span v-if="r.model" class="faint"> · {{ r.model }}</span></td>
              <td><StatusChip :status="r.source === 'client' ? (r.is_error ? 'error' : 'completed') : r.status" /><span v-if="r.source === 'client'" class="faint tnum"> {{ r.status }}</span></td>
              <td class="tnum">{{ duration(r.duration_ms ?? null) }}</td>
              <td class="tnum">{{ bill(r) }}</td>
              <td><button class="row-json" @click="open = open === r.id ? null : r.id">JSON</button></td>
            </tr>
            <tr v-if="open === r.id">
              <td colspan="8">
                <CallDetail v-if="r.source === 'model' || r.source === 'client'" :log-id="(r.log_id as string | null) ?? null" />
                <JsonBlock :title="`${r.source} ${r.id}`" :value="r" open />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <PaperButton v-if="meta.next_cursor" variant="quiet" small :disabled="loadingMore" @click="load(true)">
        {{ loadingMore ? 'Загружаем…' : 'Загрузить ещё' }}
      </PaperButton>
      <ul class="nd">
        <li v-for="(n, i) in meta.not_stored" :key="i">не хранится: {{ n }}</li>
      </ul>
    </template>
  </PlanBlock>
</template>

<style scoped>
.filters {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.pill {
  border: 1px solid var(--hairline);
  background: transparent;
  border-radius: var(--r-pill);
  padding: 4px 10px;
  font-size: 12px;
  color: var(--secondary);
  cursor: pointer;
}
.pill.on {
  color: var(--ink);
  border-color: var(--ink);
  font-weight: 600;
}
.count {
  margin: 0;
  font-size: 12px;
}
.jt {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}
.jt th,
.jt td {
  text-align: left;
  padding: 5px 8px 5px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
}
.jt th {
  font-size: 11px;
  color: var(--tertiary);
}
.jt tr.bad td {
  color: var(--verdict-unknown);
}
.what {
  word-break: break-all;
  max-width: 360px;
}
.row-json {
  border: none;
  background: transparent;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.nd {
  margin: 0;
  padding-left: 16px;
  font-size: 11.5px;
  color: var(--tertiary);
}
</style>
