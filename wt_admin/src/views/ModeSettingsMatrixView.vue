<script setup lang="ts">
/**
 * «Матрица режимов» — the full row of `learning_mode_settings` per trainer: on/off, rotation
 * position, and the acquisition-ladder threshold together. Distinct from «Тренажёры»
 * (ExerciseModesView), which edits only the on/off set as a whole ordered list — this screen edits
 * one row at a time, the same shape the honest-naming наряд (QA-20) renamed the column for
 * (`min_successful_reviews`, not `min_reps`; the /sync wire keeps the old name deliberately).
 *
 * A row saves on its own button, never on blur or on a timer — a threshold decides what a learner
 * is asked for weeks afterwards, and an accidental autosave here is not the kind of mistake that
 * should be one keystroke away from shipping.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/api'
import {
  ACQUISITION_LABEL,
  ACQUISITION_RANK,
  MODE_SETTINGS_SOURCE_LABEL,
  PHASE_SECTION_HINT,
  PHASE_SECTION_LABEL,
  modeLabel,
} from '@/utils/labels'
import type { Acquisition, ExerciseMode, ModeSettingsRow, UserRow } from '@/api/types'
import Badge from '@/components/Badge.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import StateBlock from '@/components/StateBlock.vue'

const route = useRoute()
const router = useRouter()

// Scope lives in the URL, like /ladder — a link to "the matrix as this user sees it" opens exactly
// that, and empty means the global scope (the product default).
const userId = ref<string>((route.query.user as string) ?? '')
const users = ref<UserRow[]>([])

interface Draft {
  enabled: boolean
  position: number
  minAcquisition: Acquisition
  minLearningStep: number | null
  minSuccessfulReviews: number | null
  optionsPolicy: 'distant' | 'standard'
  source: 'global' | 'override'
}

const rows = ref<ModeSettingsRow[]>([])
const drafts = reactive<Record<string, Draft>>({})
const rowSaving = reactive<Record<string, boolean>>({})
const rowResetting = reactive<Record<string, boolean>>({})
const rowError = reactive<Record<string, string | null>>({})
const loading = ref(false)
const error = ref<string | null>(null)

function toDraft(row: ModeSettingsRow): Draft {
  return {
    enabled: row.enabled,
    position: row.position,
    minAcquisition: row.minAcquisition,
    minLearningStep: row.minLearningStep,
    minSuccessfulReviews: row.minSuccessfulReviews,
    optionsPolicy: row.optionsPolicy,
    source: row.source,
  }
}

function applyMatrix(nextRows: ModeSettingsRow[]): void {
  rows.value = nextRows
  for (const row of nextRows) {
    drafts[row.mode] = toDraft(row)
    rowError[row.mode] = null
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const matrix = userId.value
      ? await api.getUserModeSettingsMatrix(userId.value)
      : await api.getModeSettingsMatrix()
    applyMatrix(matrix.rows)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    users.value = (await api.listUsers({ perPage: 200 })).data
  } catch {
    // The matrix itself still loads without the picker — a failed user list is not fatal here.
  }
  await load()
})

watch(userId, () => {
  void load()
  void router.replace({ query: { ...route.query, user: userId.value || undefined } })
})

function isDirty(row: ModeSettingsRow): boolean {
  const d = drafts[row.mode]
  if (!d) return false
  return (
    d.enabled !== row.enabled ||
    d.position !== row.position ||
    d.minAcquisition !== row.minAcquisition ||
    d.minLearningStep !== row.minLearningStep ||
    d.minSuccessfulReviews !== row.minSuccessfulReviews ||
    d.optionsPolicy !== row.optionsPolicy
  )
}

/** A threshold field only means something for the acquisition it belongs to — clear the other. */
function onAcquisitionChange(mode: ExerciseMode): void {
  const d = drafts[mode]
  if (!d) return
  if (d.minAcquisition !== 'learning') d.minLearningStep = null
  if (d.minAcquisition !== 'graduated') d.minSuccessfulReviews = null
}

function resetDraft(row: ModeSettingsRow): void {
  drafts[row.mode] = toDraft(row)
  rowError[row.mode] = null
}

async function saveRow(mode: ExerciseMode): Promise<void> {
  const d = drafts[mode]
  if (!d) return
  rowSaving[mode] = true
  rowError[mode] = null
  try {
    const payload = {
      mode,
      enabled: d.enabled,
      position: d.position,
      minAcquisition: d.minAcquisition,
      minLearningStep: d.minAcquisition === 'learning' ? d.minLearningStep : null,
      minSuccessfulReviews: d.minAcquisition === 'graduated' ? d.minSuccessfulReviews : null,
      optionsPolicy: d.optionsPolicy,
    }
    const matrix = userId.value
      ? await api.saveUserModeSettingsRow(userId.value, payload)
      : await api.saveModeSettingsRow(payload)
    applyMatrix(matrix.rows)
  } catch (e) {
    rowError[mode] = e instanceof Error ? e.message : 'Не удалось сохранить'
  } finally {
    rowSaving[mode] = false
  }
}

async function resetOverride(mode: ExerciseMode): Promise<void> {
  if (!userId.value) return
  rowResetting[mode] = true
  rowError[mode] = null
  try {
    const matrix = await api.resetUserModeSettingsOverride(userId.value, mode)
    applyMatrix(matrix.rows)
  } catch (e) {
    rowError[mode] = e instanceof Error ? e.message : 'Не удалось сбросить'
  } finally {
    rowResetting[mode] = false
  }
}

const columns: Column[] = [
  { key: 'mode', label: 'Режим', width: '18%' },
  { key: 'enabled', label: 'Вкл', align: 'center', width: '54px' },
  { key: 'position', label: 'Позиция', align: 'right', width: '84px' },
  { key: 'min_acquisition', label: 'Открывается с', width: '170px' },
  { key: 'min_learning_step', label: 'Шаг', align: 'right', width: '76px' },
  { key: 'min_successful_reviews', label: 'Повторы', align: 'right', width: '90px' },
  { key: 'options_policy', label: 'Опции', width: '130px' },
  { key: 'source', label: 'Источник', width: '84px' },
  { key: 'actions', label: '' },
]

// ── Sectioning by EFFECTIVE opening phase (max(passport floor, configured threshold)) ──────────

/** What the row is CONFIGURED to right now — the live draft while it's being edited, else the
 *  saved value. Reading through the draft is what makes changing the selector move the row
 *  between sections immediately, before the row is saved. */
function configuredPhase(row: ModeSettingsRow): Acquisition {
  return drafts[row.mode]?.minAcquisition ?? row.minAcquisition
}

/** A legacy row (written before the passport floor existed, or by a rolled-back build) can still
 *  read below its own mode's floor — the backend refuses new writes below it, but never rewrites
 *  data behind an admin's back. Such a row is asked for by a phase it cannot actually open in. */
function isBelowFloor(row: ModeSettingsRow): boolean {
  return ACQUISITION_RANK[configuredPhase(row)] < ACQUISITION_RANK[row.floor]
}

/** The phase this row ACTUALLY opens in — the configured threshold, floored at the passport. */
function effectivePhase(row: ModeSettingsRow): Acquisition {
  return isBelowFloor(row) ? row.floor : configuredPhase(row)
}

/** Is this "Открывается с" option unreachable for this row — below its mode's passport floor? */
function optionBelowFloor(row: ModeSettingsRow, option: Acquisition): boolean {
  return ACQUISITION_RANK[option] < ACQUISITION_RANK[row.floor]
}

function floorTooltip(row: ModeSettingsRow, option: Acquisition): string {
  return `«${ACQUISITION_LABEL[option]}» недоступно для «${modeLabel(row.mode)}»: минимум — «${ACQUISITION_LABEL[row.floor]}».`
}

const PHASE_ORDER: Acquisition[] = ['new', 'learning', 'graduated']

interface Section {
  phase: Acquisition
  title: string
  hint: string
  rows: ModeSettingsRow[]
}

/** Rows keep the server's position-then-mode order within each section — `filter` preserves it.
 *  An empty phase (no row opens there, in this scope) drops out rather than rendering an empty
 *  card. */
const sections = computed<Section[]>(() =>
  PHASE_ORDER.map((phase) => ({
    phase,
    title: PHASE_SECTION_LABEL[phase],
    hint: PHASE_SECTION_HINT[phase],
    rows: rows.value.filter((row) => effectivePhase(row) === phase),
  })).filter((section) => section.rows.length > 0),
)
</script>

<template>
  <div>
    <PageHeader
      title="Матрица режимов"
      subtitle="Полная строка тренажёра — включён ли, где в ротации, с какого рубежа лестницы открывается. Сохранение построчное."
    />

    <div class="controls">
      <label class="f">
        <span class="section-label">Пользователь</span>
        <select v-model="userId">
          <option value="">Глобально (общий набор)</option>
          <option v-for="u in users" :key="u.id" :value="u.id">
            {{ u.name }}{{ u.email ? ` · ${u.email}` : '' }}
          </option>
        </select>
      </label>
      <span v-if="userId" class="hint faint">
        «своё» — персональный override этой строки; «общее» — унаследовано и меняется вместе с общим набором.
      </span>
    </div>

    <StateBlock v-if="loading && rows.length === 0" kind="loading" />
    <StateBlock v-else-if="error && rows.length === 0" kind="error" :message="error" retryable @retry="load" />

    <template v-else>
      <section v-for="section in sections" :key="section.phase" class="phase-section">
        <div class="section-head">
          <h3 class="serif">{{ section.title }}</h3>
          <p class="faint sub">{{ section.hint }}</p>
        </div>
        <PaperCard :pad="false">
          <DataTable :columns="columns" :rows="section.rows" :row-key="(r) => r.mode">
            <template #cell-mode="{ row }">
              <div class="mode-cell">
                <span class="name">{{ modeLabel(row.mode) }}</span>
                <span class="wire faint tnum">{{ row.mode }}</span>
              </div>
            </template>
            <template #cell-enabled="{ row }">
              <input
                v-if="drafts[row.mode]"
                v-model="drafts[row.mode].enabled"
                type="checkbox"
                :disabled="rowSaving[row.mode]"
              />
            </template>
            <template #cell-position="{ row }">
              <input
                v-if="drafts[row.mode]"
                v-model.number="drafts[row.mode].position"
                type="number"
                min="0"
                step="1"
                class="num"
                :disabled="rowSaving[row.mode]"
              />
            </template>
            <template #cell-min_acquisition="{ row }">
              <select
                v-if="drafts[row.mode]"
                v-model="drafts[row.mode].minAcquisition"
                class="field"
                :disabled="rowSaving[row.mode]"
                @change="onAcquisitionChange(row.mode)"
              >
                <option
                  v-for="(label, key) in ACQUISITION_LABEL"
                  :key="key"
                  :value="key"
                  :disabled="optionBelowFloor(row, key)"
                  :title="optionBelowFloor(row, key) ? floorTooltip(row, key) : undefined"
                >
                  {{ label }}
                </option>
              </select>
              <p v-if="isBelowFloor(row)" class="legacy-note" :title="floorTooltip(row, configuredPhase(row))">
                фактически: {{ ACQUISITION_LABEL[row.floor] }}
              </p>
            </template>
            <template #cell-min_learning_step="{ row }">
              <input
                v-if="drafts[row.mode]"
                v-model.number="drafts[row.mode].minLearningStep"
                type="number"
                min="0"
                max="2"
                step="1"
                class="num"
                :disabled="rowSaving[row.mode] || drafts[row.mode].minAcquisition !== 'learning'"
                :placeholder="drafts[row.mode].minAcquisition === 'learning' ? '0–2' : '—'"
              />
            </template>
            <template #cell-min_successful_reviews="{ row }">
              <input
                v-if="drafts[row.mode]"
                v-model.number="drafts[row.mode].minSuccessfulReviews"
                type="number"
                min="0"
                step="1"
                class="num"
                :disabled="rowSaving[row.mode] || drafts[row.mode].minAcquisition !== 'graduated'"
                :placeholder="drafts[row.mode].minAcquisition === 'graduated' ? 'нет' : '—'"
              />
            </template>
            <template #cell-options_policy="{ row }">
              <select
                v-if="drafts[row.mode]"
                v-model="drafts[row.mode].optionsPolicy"
                class="field"
                :disabled="rowSaving[row.mode]"
              >
                <option value="standard">обычные</option>
                <option value="distant">далёкие</option>
              </select>
            </template>
            <template #cell-source="{ row }">
              <Badge v-if="drafts[row.mode]" :tone="drafts[row.mode].source === 'override' ? 'unsure' : 'neutral'">
                {{ MODE_SETTINGS_SOURCE_LABEL[drafts[row.mode].source] }}
              </Badge>
            </template>
            <template #cell-actions="{ row }">
              <div class="row-actions">
                <PaperButton small :disabled="!isDirty(row) || rowSaving[row.mode]" @click="saveRow(row.mode)">
                  {{ rowSaving[row.mode] ? '…' : 'Сохранить' }}
                </PaperButton>
                <PaperButton
                  v-if="isDirty(row)"
                  variant="ghost"
                  small
                  :disabled="rowSaving[row.mode]"
                  @click="resetDraft(row)"
                >
                  Отменить
                </PaperButton>
                <PaperButton
                  v-if="userId && drafts[row.mode]?.source === 'override'"
                  variant="ghost"
                  small
                  :disabled="rowResetting[row.mode]"
                  @click="resetOverride(row.mode)"
                >
                  {{ rowResetting[row.mode] ? '…' : 'Сбросить' }}
                </PaperButton>
              </div>
              <p v-if="rowError[row.mode]" class="row-err">{{ rowError[row.mode] }}</p>
            </template>
          </DataTable>
        </PaperCard>
      </section>
    </template>
  </div>
</template>

<style scoped>
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
  max-width: 340px;
}
.hint {
  font-size: 12.5px;
  padding-bottom: 9px;
  max-width: 420px;
}
.phase-section {
  margin-bottom: var(--s16);
}
.phase-section:last-child {
  margin-bottom: 0;
}
.section-head {
  margin-bottom: var(--s12);
}
.section-head h3 {
  margin: 0;
  font-size: 16px;
}
.section-head .sub {
  margin: 2px 0 0;
  font-size: 12px;
}
.legacy-note {
  margin: 3px 0 0;
  font-size: 11px;
  color: var(--destructive-text, #9a4b3f);
  cursor: help;
}
.mode-cell {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.name {
  font-weight: 600;
}
.wire {
  font-size: 11px;
}
.num {
  width: 64px;
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-small);
  padding: 5px 7px;
  font-size: 13px;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}
.num:disabled {
  opacity: 0.5;
}
.field {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-small);
  padding: 5px 7px;
  font-size: 13px;
  color: var(--ink);
}
.row-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.row-err {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--destructive-text, #9a4b3f);
}
</style>
