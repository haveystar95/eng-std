<script setup lang="ts">
/**
 * The request log, sliced.
 *
 * Filters live in the URL query, not in component state: that is what makes a view shareable —
 * paste the link and a colleague sees the same slice. Which also means the screen has to be driven
 * FROM the route (a watcher), never from the inputs directly.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/api'
import { useInfinite } from '@/composables/useInfinite'
import { money } from '@/utils/format'
import type { CallPurpose, LogsQuery, RequestLog, RequestLogDetail } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import PaperButton from '@/components/PaperButton.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import InfiniteList from '@/components/InfiniteList.vue'
import JsonBlock from '@/components/JsonBlock.vue'

const route = useRoute()
const router = useRouter()

const PURPOSES: { key: '' | CallPurpose; label: string }[] = [
  { key: '', label: 'Все цели' },
  { key: 'generation', label: 'Генерация' },
  { key: 'images', label: 'Картинки' },
  { key: 'enrichment', label: 'Станок' },
  { key: 'realtime', label: 'Реалтайм' },
  { key: 'recap', label: 'Разбор' },
  { key: 'example_regen', label: 'Новый пример' },
]

/** The filter form. Seeded from the URL and written back to it on «Применить». */
const form = ref(fromRoute())

function fromRoute(): Required<Pick<LogsQuery, never>> & Record<string, string> {
  const q = route.query
  const s = (k: string) => (typeof q[k] === 'string' ? (q[k] as string) : '')
  return {
    direction: s('direction'),
    provider: s('provider'),
    purpose: s('purpose'),
    status: s('status'),
    statusClass: s('status_class'),
    userId: s('user_id'),
    collectionId: s('collection_id'),
    from: s('from'),
    to: s('to'),
    path: s('path'),
    search: s('search'),
  }
}

function query(): LogsQuery {
  const f = form.value
  return {
    direction: (f.direction || undefined) as LogsQuery['direction'],
    provider: f.provider || undefined,
    purpose: (f.purpose || undefined) as CallPurpose | undefined,
    status: f.status ? Number(f.status) : undefined,
    statusClass: (f.statusClass || undefined) as LogsQuery['statusClass'],
    userId: f.userId || undefined,
    collectionId: f.collectionId || undefined,
    from: f.from ? new Date(f.from).toISOString() : undefined,
    to: f.to ? new Date(f.to + 'T23:59:59').toISOString() : undefined,
    path: f.path || undefined,
    search: f.search || undefined,
  }
}

const list = useInfinite<RequestLog>((q) => api.listLogs({ ...query(), ...q }))

// The route is the source of truth: pushing a new query re-seeds the form and reloads.
watch(
  () => route.query,
  () => {
    form.value = fromRoute()
    list.reload()
  },
  { immediate: true },
)

const activeCount = computed(() => Object.values(form.value).filter(Boolean).length)

function apply() {
  const f = form.value
  router.push({
    name: 'logs',
    query: Object.fromEntries(
      Object.entries({
        direction: f.direction,
        provider: f.provider,
        purpose: f.purpose,
        status: f.status,
        status_class: f.statusClass,
        user_id: f.userId,
        collection_id: f.collectionId,
        from: f.from,
        to: f.to,
        path: f.path,
        search: f.search,
      }).filter(([, v]) => v !== ''),
    ),
  })
}
function clearAll() {
  router.push({ name: 'logs', query: {} })
}

// ── Row expansion: bodies are fetched per row, not shipped with the page ──
const openId = ref<string | null>(null)
const detail = ref<RequestLogDetail | null>(null)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)

async function toggleRow(row: RequestLog) {
  if (openId.value === row.id) {
    openId.value = null
    return
  }
  openId.value = row.id
  detail.value = null
  detailError.value = null
  detailLoading.value = true
  try {
    detail.value = await api.getLog(row.id)
  } catch (e) {
    detailError.value = e instanceof Error ? e.message : 'Не удалось загрузить тела'
  } finally {
    detailLoading.value = false
  }
}

// Maps onto the paper palette's verdict tones: known = fine, unsure = client error, unknown = ours.
function statusTone(status: number | null): 'known' | 'unsure' | 'unknown' | 'neutral' {
  if (status === null || status >= 500) return 'unknown'
  if (status >= 400) return 'unsure'
  if (status >= 200 && status < 300) return 'known'
  return 'neutral'
}
</script>

<template>
  <div>
    <PageHeader
      title="Логи"
      subtitle="Входящие запросы к API и исходящие вызовы провайдеров. Фильтры живут в адресе — ссылкой можно поделиться."
    >
      <template #actions>
        <PaperButton variant="quiet" small @click="list.reload()">Обновить</PaperButton>
      </template>
    </PageHeader>

    <PaperCard class="filters">
      <div class="grid">
        <label class="f">
          <span class="section-label">Направление</span>
          <select v-model="form.direction">
            <option value="">Все</option>
            <option value="inbound">Входящие</option>
            <option value="outbound">Исходящие</option>
          </select>
        </label>
        <label class="f">
          <span class="section-label">Цель</span>
          <select v-model="form.purpose">
            <option v-for="p in PURPOSES" :key="p.key" :value="p.key">{{ p.label }}</option>
          </select>
        </label>
        <label class="f">
          <span class="section-label">Провайдер</span>
          <select v-model="form.provider">
            <option value="">Все</option>
            <option value="openai">OpenAI</option>
            <option value="pexels">Pexels</option>
            <option value="gemini">Gemini</option>
          </select>
        </label>
        <label class="f">
          <span class="section-label">Статус</span>
          <select v-model="form.statusClass">
            <option value="">Любой</option>
            <option value="2xx">2xx</option>
            <option value="4xx">4xx</option>
            <option value="5xx">5xx</option>
            <option value="error">Не дошёл</option>
          </select>
        </label>
        <label class="f">
          <span class="section-label">С даты</span>
          <input v-model="form.from" type="date" />
        </label>
        <label class="f">
          <span class="section-label">По дату</span>
          <input v-model="form.to" type="date" />
        </label>
        <label class="f grow">
          <span class="section-label">Пользователь (ULID)</span>
          <input v-model="form.userId" type="text" placeholder="01K…" />
        </label>
        <label class="f grow">
          <span class="section-label">Коллекция (ULID)</span>
          <input v-model="form.collectionId" type="text" placeholder="01K…" />
        </label>
        <label class="f grow">
          <span class="section-label">Путь</span>
          <input v-model="form.path" type="text" placeholder="/v1/chat…" />
        </label>
        <label class="f grow">
          <span class="section-label">Поиск в телах</span>
          <input v-model="form.search" type="text" placeholder="слово из запроса или ответа" @keyup.enter="apply" />
        </label>
      </div>
      <div class="actions">
        <PaperButton small @click="apply">Применить</PaperButton>
        <PaperButton v-if="activeCount" variant="ghost" small @click="clearAll">
          Сбросить ({{ activeCount }})
        </PaperButton>
      </div>
    </PaperCard>

    <PaperCard :pad="false" class="wrap">
      <InfiniteList
        :loading="list.loading.value"
        :loading-more="list.loadingMore.value"
        :error="list.error.value"
        :done="list.done.value"
        :count="list.rows.value.length"
        :total="list.total.value"
        empty-message="По этим фильтрам ничего нет"
        @more="list.loadMore()"
        @retry="list.reload()"
      >
        <table class="ptable sticky">
          <thead>
            <tr>
              <th style="width: 150px">Когда</th>
              <th style="width: 120px">Цель</th>
              <th style="width: 110px">Провайдер</th>
              <th style="width: 90px">Статус</th>
              <th style="width: 130px">Модель</th>
              <th style="width: 120px" class="right">Токены</th>
              <th style="width: 100px" class="right">Стоимость</th>
              <th style="width: 16%">Юзер</th>
              <th>Коллекция / путь</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in list.rows.value" :key="row.id">
              <tr class="clickable" @click="toggleRow(row)">
                <td><RelativeDate :value="row.occurredAt" /></td>
                <td>
                  <Badge v-if="row.purpose">{{ PURPOSES.find((p) => p.key === row.purpose)?.label ?? row.purpose }}</Badge>
                  <span v-else class="faint">—</span>
                </td>
                <td>{{ row.service ?? (row.direction === 'inbound' ? 'наш API' : '—') }}</td>
                <td>
                  <Badge :tone="statusTone(row.status)">{{ row.status ?? 'ошибка' }}</Badge>
                </td>
                <td class="mono">{{ row.model ?? '—' }}</td>
                <td class="right tnum">
                  <span v-if="row.tokensIn !== null">{{ row.tokensIn }} / {{ row.tokensOut }}</span>
                  <span v-else class="faint">—</span>
                </td>
                <td class="right tnum">
                  <span v-if="row.costUsd">{{ money(row.costUsd) }}</span>
                  <span v-else class="faint">—</span>
                </td>
                <td class="trunc" :title="row.userId ?? ''">
                  <RouterLink
                    v-if="row.userId"
                    :to="{ name: 'user', params: { id: row.userId, tab: 'logs' } }"
                    class="link"
                    @click.stop
                  >
                    ···{{ row.userId.slice(-6) }}
                  </RouterLink>
                  <span v-else class="faint">—</span>
                </td>
                <td class="trunc" :title="row.path">
                  <RouterLink
                    v-if="row.collectionId"
                    :to="{ name: 'collection', params: { id: row.collectionId } }"
                    class="link"
                    @click.stop
                  >
                    коллекция ···{{ row.collectionId.slice(-6) }}
                  </RouterLink>
                  <span class="path">{{ row.method }} {{ row.path }}</span>
                </td>
              </tr>
              <tr v-if="openId === row.id" class="detail-row">
                <td colspan="9">
                  <div v-if="detailLoading" class="faint">Загружаем тела…</div>
                  <div v-else-if="detailError" class="err">{{ detailError }}</div>
                  <div v-else-if="detail" class="detail">
                    <p v-if="detail.error" class="err">Транспортная ошибка: {{ detail.error }}</p>
                    <JsonBlock title="Заголовки запроса" :value="detail.requestHeaders" />
                    <JsonBlock title="Тело запроса" :value="detail.requestBody" open />
                    <JsonBlock title="Тело ответа" :value="detail.responseBody" open />
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </InfiniteList>
    </PaperCard>
  </div>
</template>

<style scoped>
.filters {
  margin-bottom: var(--s16);
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: var(--s12);
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f input,
.f select {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 10px;
  font-size: 13.5px;
  height: 36px;
  width: 100%;
}
.actions {
  display: flex;
  gap: var(--s8, 8px);
  margin-top: var(--s12);
}
.wrap {
  overflow: hidden;
}
.ptable {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ptable.sticky thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--paper, #fff);
}
thead th {
  text-align: left;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--secondary);
  border-bottom: 1px solid var(--hairline);
}
tbody td {
  padding: 9px 12px;
  border-bottom: 1px solid var(--divider-faint);
  vertical-align: middle;
}
tr.clickable {
  cursor: pointer;
}
tr.clickable:hover td {
  background: var(--faint-ink);
}
.right {
  text-align: right;
}
.trunc {
  max-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
}
.path {
  color: var(--secondary);
  font-size: 12px;
  margin-left: 6px;
}
.link {
  color: var(--ink);
  text-decoration: underline;
}
.detail-row td {
  background: var(--faint-ink);
}
.detail {
  display: flex;
  flex-direction: column;
  gap: var(--s8, 8px);
  padding: var(--s8, 8px) 0;
}
.err {
  color: var(--destructive, #b4423a);
  font-size: 12.5px;
}
</style>
