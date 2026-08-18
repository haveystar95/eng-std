<script setup lang="ts">
/**
 * «Прогресс обучения» — the ladder, watched live.
 *
 * This screen exists to be looked at WHILE a session is happening on the phone: pick the learner,
 * leave it polling, and every answer shows up in the feed within seconds and moves a dot in the
 * table. It only observes — nothing here writes, and the ladder's controls (which trainer is
 * admitted at which rung) live on their own screen.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Activity, Eye, ScanEye, Sparkles } from 'lucide-vue-next'
import { api } from '@/api'
import { usePolling } from '@/composables/usePolling'
import { plural } from '@/utils/format'
import { MODE_LABEL, STATE_LABEL, stateTone, TRIAGE_VERDICT_LABEL, triageVerdictTone } from '@/utils/labels'
import type { LadderCounts, LadderEvent, LadderLearner, LadderPair, LadderPhase, UserDetail } from '@/api/types'
import Badge from '@/components/Badge.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import LadderDots from '@/components/LadderDots.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import Pagination from '@/components/Pagination.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StateBlock from '@/components/StateBlock.vue'

const route = useRoute()
const router = useRouter()

const learners = ref<LadderLearner[]>([])
const userId = ref<string>((route.query.user as string) ?? '')
const detail = ref<UserDetail | null>(null)

const phase = ref<LadderPhase | ''>((route.query.phase as LadderPhase) ?? '')
const collectionId = ref<string>((route.query.collection as string) ?? '')
const dueOnly = ref(route.query.due === '1')
const page = ref(Number(route.query.page ?? 1) || 1)

const pairs = ref<LadderPair[]>([])
const counts = ref<LadderCounts | null>(null)
const meta = ref({ page: 1, perPage: 25, total: 0, totalPages: 1, nextCursor: null as string | null })
const events = ref<LadderEvent[]>([])
const error = ref<string | null>(null)
const firstLoad = ref(true)

const EMPTY_COUNTS: LadderCounts = { total: 0, new: 0, learning: 0, graduated: 0, known: 0, due: 0 }

/** The two polled reads, together — the table and the feed must describe the same moment. */
async function refresh(): Promise<void> {
  if (!userId.value) return
  try {
    const [progress, feed] = await Promise.all([
      api.getLadderProgress(userId.value, {
        collectionId: collectionId.value || undefined,
        phase: phase.value || undefined,
        due: dueOnly.value || undefined,
        page: page.value,
        perPage: 25,
      }),
      api.getLadderEvents(userId.value, 50),
    ])
    pairs.value = progress.data
    counts.value = progress.counts
    meta.value = progress.meta
    events.value = feed
    error.value = null
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    throw e
  } finally {
    firstLoad.value = false
  }
}

const { enabled, busy, secondsAgo, run, start } = usePolling(refresh, 4000)

onMounted(async () => {
  try {
    learners.value = await api.listLadderLearners()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось получить список пользователей'
  }
  // Default to whoever used the app most recently — on a one-person install that is always the
  // right guess, and it means the screen is useful the moment it opens.
  if (!userId.value && learners.value.length > 0) userId.value = learners.value[0].id
  await loadUser()
  start()
})

async function loadUser(): Promise<void> {
  if (!userId.value) {
    firstLoad.value = false
    return
  }
  detail.value = await api.getUser(userId.value).catch(() => null)
  await run()
}

watch(userId, async () => {
  // A new learner invalidates the collection filter — it is a list of THEIR collections.
  collectionId.value = ''
  page.value = 1
  firstLoad.value = true
  await loadUser()
})
watch([phase, collectionId, dueOnly], () => {
  page.value = 1
  void run()
})
watch(page, () => void run())

// Every filter lives in the URL, like the rest of the panel: the view can be pasted to someone (or
// to yourself, tomorrow) and it opens on exactly the same slice.
watch([userId, phase, collectionId, dueOnly, page], () => {
  void router.replace({
    query: {
      user: userId.value || undefined,
      phase: phase.value || undefined,
      collection: collectionId.value || undefined,
      due: dueOnly.value ? '1' : undefined,
      page: page.value > 1 ? String(page.value) : undefined,
    },
  })
})

const c = computed<LadderCounts>(() => counts.value ?? EMPTY_COUNTS)
const tiles = computed(() => [
  { key: '' as const, label: 'Все', value: c.value.total },
  { key: 'new' as const, label: 'Новые', value: c.value.new },
  { key: 'learning' as const, label: 'Узнавание', value: c.value.learning },
  { key: 'graduated' as const, label: 'Выпущены', value: c.value.graduated },
  { key: 'known' as const, label: '«Знаю»', value: c.value.known },
])

const freshness = computed(() => {
  if (!enabled.value) return 'обновление на паузе'
  if (secondsAgo.value < 2) return 'обновлено только что'
  return `обновлено ${secondsAgo.value} ${plural(secondsAgo.value, 'секунду', 'секунды', 'секунд')} назад`
})

/** A poll that has not landed for a while is a warning, not a detail: the screen may be lying. */
const stale = computed(() => enabled.value && secondsAgo.value > 15)

function selectPhase(key: LadderPhase | ''): void {
  phase.value = phase.value === key ? '' : key
}

function eventVerdict(e: LadderEvent): 'known' | 'unsure' | 'unknown' | 'neutral' {
  if (e.kind === 'exposure') return 'neutral'
  if (e.kind === 'triage') return e.verdict !== null ? triageVerdictTone(e.verdict) : 'neutral'
  if (e.isCorrect === null) return 'neutral'
  return e.isCorrect ? 'known' : 'unknown'
}

/**
 * «Срок» fallback text for a pair with no due date — the reasons read differently on purpose:
 * a mid-ladder pair has no due date AT ALL yet (dash, as before); a released one is simply awaiting
 * its first SM-2 review, which is worth saying rather than drawing the same hole-shaped dash; a
 * triaged «знаю» pair is outside the ladder entirely and keeps the dash it always had.
 */
function dueFallback(row: LadderPair): string {
  return row.state !== 'known' && row.acquisition === 'graduated' ? 'к первому повторению' : '—'
}

const columns: Column[] = [
  { key: 'word', label: 'Слово', width: '30%' },
  { key: 'ladder', label: 'Ступень', width: '110px' },
  { key: 'state', label: 'SM-2' },
  { key: 'reps', label: 'Повт.', align: 'right', tnum: true },
  { key: 'due', label: 'Срок', align: 'right' },
  { key: 'intro', label: 'Интро', align: 'center' },
  { key: 'last', label: 'Последний ответ' },
]
</script>

<template>
  <div>
    <PageHeader
      title="Прогресс обучения"
      subtitle="Где каждое слово стоит на лестнице и что телефон только что сделал. Только чтение."
    >
      <template #actions>
        <label class="live" :class="{ off: !enabled }">
          <input v-model="enabled" type="checkbox" />
          <span class="pulse" :class="{ beat: enabled && busy }" />
          <span>Автообновление</span>
        </label>
        <span class="freshness faint" :class="{ stale }">{{ freshness }}</span>
        <PaperButton variant="quiet" small :disabled="busy" @click="run">Обновить</PaperButton>
      </template>
    </PageHeader>

    <div class="controls">
      <label class="f">
        <span class="section-label">Пользователь</span>
        <select v-model="userId">
          <option v-for="l in learners" :key="l.id" :value="l.id">
            {{ l.name }}{{ l.email ? ` · ${l.email}` : '' }} — {{ l.pairsCount }}
          </option>
        </select>
      </label>
      <label class="f">
        <span class="section-label">Коллекция</span>
        <select v-model="collectionId">
          <option value="">все</option>
          <option v-for="col in detail?.collections ?? []" :key="col.id" :value="col.id">{{ col.title }}</option>
        </select>
      </label>
      <label class="check">
        <input v-model="dueOnly" type="checkbox" />
        <span>только к повтору<span v-if="counts" class="cnt tnum"> ({{ c.due }})</span></span>
      </label>
      <span v-if="learners.length" class="last-seen faint">
        последняя активность:
        <RelativeDate :value="learners.find((l) => l.id === userId)?.lastActivityAt ?? null" />
      </span>
    </div>

    <div class="tiles">
      <button
        v-for="t in tiles"
        :key="t.key || 'all'"
        class="tile"
        :class="{ active: phase === t.key }"
        @click="selectPhase(t.key)"
      >
        <span class="tile-label">{{ t.label }}</span>
        <span class="tile-value tnum">{{ t.value }}</span>
      </button>
    </div>

    <div class="grid">
      <PaperCard :pad="false" class="table-card">
        <StateBlock v-if="firstLoad && pairs.length === 0 && !error" kind="loading" />
        <StateBlock v-else-if="error && pairs.length === 0" kind="error" :message="error" retryable @retry="run" />
        <StateBlock
          v-else-if="pairs.length === 0"
          kind="empty"
          title="Пар нет"
          message="Слово попадает сюда после первой встречи — интро, ответа или триажа. Нетронутые слова коллекции строки ещё не имеют."
        />
        <template v-else>
          <DataTable :columns="columns" :rows="pairs" :row-key="(p) => p.termId" sticky-header>
            <template #cell-word="{ row }">
              <div class="word">
                <span class="serif term">{{ row.text }}</span>
                <span class="tr faint">{{ row.translation ?? '—' }}</span>
              </div>
              <div v-if="row.collections.length" class="cols faint">
                {{ row.collections.map((x) => x.title).join(' · ') }}
              </div>
            </template>
            <template #cell-ladder="{ row }"><LadderDots :step="row.ladderStep" /></template>
            <template #cell-state="{ row }">
              <!-- The scheduler's state, NOT the ladder — the two are orthogonal and this is the one
                   place they sit side by side, so a pair reading «изучается» here while standing on
                   rung 3 is correct, not a bug. Spelled out in the tooltip rather than in a second
                   column, which would double the width of the table to say it. -->
              <Badge
                :tone="stateTone(row.state)"
                :title="`планировщик SM-2: ${STATE_LABEL[row.state]} · лестница: ${row.acquisition}`"
              >
                {{ STATE_LABEL[row.state] }}
              </Badge>
            </template>
            <template #cell-reps="{ row }">{{ row.reps }}</template>
            <template #cell-due="{ row }">
              <RelativeDate v-if="row.dueAt" :value="row.dueAt" />
              <span v-else class="faint">{{ dueFallback(row) }}</span>
            </template>
            <template #cell-intro="{ row }">
              <span v-if="row.exposedAt" class="ok" :title="`интро показано`">✓</span>
              <span v-else class="faint">—</span>
            </template>
            <template #cell-last="{ row }">
              <template v-if="row.lastReview">
                <span :class="row.lastReview.isCorrect === false ? 'no' : 'ok'">
                  {{ row.lastReview.isCorrect === false ? '✕' : '✓' }}
                </span>
                <Badge v-if="row.lastReview.exerciseMode" class="mode">
                  {{ MODE_LABEL[row.lastReview.exerciseMode] }}
                </Badge>
                <Badge v-if="row.lastReview.isPractice" tone="unsure">практика</Badge>
                <RelativeDate class="when" :value="row.lastReview.answeredAt" />
              </template>
              <span v-else class="faint">ещё не отвечал</span>
            </template>
          </DataTable>
          <div class="pad"><Pagination :meta="meta" @change="(p) => (page = p)" /></div>
        </template>
      </PaperCard>

      <PaperCard :pad="false" class="feed-card">
        <div class="feed-head">
          <Activity :size="15" />
          <span>Лента событий</span>
          <span class="faint feed-hint">свежие сверху</span>
        </div>
        <StateBlock
          v-if="events.length === 0"
          kind="empty"
          title="Тихо"
          message="Ни ответов, ни показанных карточек."
        />
        <ul v-else class="feed">
          <li v-for="e in events" :key="e.id" class="ev" :class="eventVerdict(e)">
            <div class="ev-time faint"><RelativeDate :value="e.occurredAt" /></div>
            <div class="ev-body">
              <span class="serif ev-term">{{ e.termText ?? '—' }}</span>
              <span class="ev-meta">
                <template v-if="e.kind === 'exposure'">
                  <Sparkles :size="12" class="ev-icon" />
                  <span>интро</span>
                </template>
                <template v-else-if="e.kind === 'triage'">
                  <ScanEye :size="12" class="ev-icon" />
                  <span>триаж</span>
                  <span v-if="e.verdict" class="ev-verdict">{{ TRIAGE_VERDICT_LABEL[e.verdict] }}</span>
                </template>
                <template v-else>
                  <Eye :size="12" class="ev-icon" />
                  <span v-if="e.exerciseMode">{{ MODE_LABEL[e.exerciseMode] }}</span>
                  <span v-if="e.ladderStep !== null" class="ev-step">ст. {{ e.ladderStep }}</span>
                  <span class="ev-verdict">{{ e.isCorrect === false ? '✕ мимо' : '✓ верно' }}</span>
                  <span v-if="e.isPractice" class="ev-practice">практика</span>
                </template>
              </span>
            </div>
          </li>
        </ul>
      </PaperCard>
    </div>
  </div>
</template>

<style scoped>
.live {
  display: inline-flex;
  align-items: center;
  gap: var(--s8);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.live.off {
  color: var(--secondary);
}
.pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--track);
}
.live:not(.off) .pulse {
  background: var(--verdict-known);
}
.pulse.beat {
  animation: beat 0.8s ease-in-out infinite;
}
@keyframes beat {
  50% {
    opacity: 0.25;
  }
}
.freshness {
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}
.freshness.stale {
  color: var(--verdict-unsure);
  font-weight: 600;
}
.controls {
  display: flex;
  align-items: flex-end;
  gap: var(--s16);
  flex-wrap: wrap;
  margin-bottom: var(--s16);
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f select {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
  color: var(--ink);
  max-width: 320px;
}
.check {
  display: inline-flex;
  align-items: center;
  gap: var(--s8);
  font-size: 13.5px;
  padding-bottom: 9px;
  cursor: pointer;
}
.cnt {
  color: var(--secondary);
}
.last-seen {
  font-size: 12.5px;
  padding-bottom: 10px;
  margin-left: auto;
}
.tiles {
  display: flex;
  gap: var(--s12);
  flex-wrap: wrap;
  margin-bottom: var(--s16);
}
.tile {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 108px;
  padding: 10px var(--s16);
  border: none;
  border-radius: var(--r-small);
  background: var(--surface-raised);
  box-shadow: var(--shadow-card);
  text-align: left;
  cursor: pointer;
}
.tile.active {
  background: var(--ink);
  color: var(--paper);
}
.tile-label {
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--secondary);
}
.tile.active .tile-label {
  color: var(--paper);
  opacity: 0.72;
}
.tile-value {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: var(--s16);
  align-items: start;
}
@media (max-width: 1100px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
.table-card {
  overflow: hidden;
}
.pad {
  padding: 0 var(--s16) var(--s16);
}
.word {
  display: flex;
  align-items: baseline;
  gap: var(--s8);
  flex-wrap: wrap;
}
.term {
  font-size: 16px;
  /* A multi-word term is one thing to read. Broken across two lines it stops looking like the card
     the learner was shown, which is the only thing this column is for. */
  white-space: nowrap;
}
.tr {
  font-size: 13px;
}
.cols {
  font-size: 11.5px;
  margin-top: 2px;
}
.mode,
.when {
  margin-left: var(--s8);
}
.when {
  font-size: 12px;
}
.ok {
  color: var(--verdict-known);
  font-weight: 700;
}
.no {
  color: var(--verdict-unknown);
  font-weight: 700;
}
.feed-card {
  position: sticky;
  top: var(--s16);
  max-height: calc(100vh - 120px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.feed-head {
  display: flex;
  align-items: center;
  gap: var(--s8);
  padding: var(--s12) var(--s16);
  border-bottom: 1px solid var(--hairline);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--secondary);
}
.feed-hint {
  margin-left: auto;
  letter-spacing: 0;
  text-transform: none;
  font-weight: 500;
  font-size: 11px;
}
.feed {
  list-style: none;
  margin: 0;
  padding: 0;
  overflow-y: auto;
}
.ev {
  display: flex;
  gap: var(--s12);
  padding: 9px var(--s16);
  border-bottom: 1px solid var(--divider-faint);
  border-left: 2px solid transparent;
}
.ev:last-child {
  border-bottom: none;
}
.ev.known {
  border-left-color: var(--verdict-known);
}
.ev.unsure {
  border-left-color: var(--verdict-unsure);
}
.ev.unknown {
  border-left-color: var(--verdict-unknown);
}
.ev-time {
  font-size: 11.5px;
  min-width: 86px;
  padding-top: 2px;
}
.ev-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.ev-term {
  font-size: 14.5px;
}
.ev-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  font-size: 11.5px;
  color: var(--secondary);
}
.ev-icon {
  flex-shrink: 0;
}
.ev-step,
.ev-practice {
  padding: 1px 6px;
  border-radius: var(--r-pill);
  background: var(--faint-ink);
}
.ev.known .ev-verdict {
  color: var(--verdict-known);
  font-weight: 600;
}
.ev.unsure .ev-verdict {
  color: var(--verdict-unsure);
  font-weight: 600;
}
.ev.unknown .ev-verdict {
  color: var(--verdict-unknown);
  font-weight: 600;
}
</style>
