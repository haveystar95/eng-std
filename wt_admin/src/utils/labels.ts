// Domain → display mappings (Russian labels + paper-palette tones).
import type {
  Acquisition,
  ContentGap,
  ContentStatus,
  NeedsEnrichmentReason,
  CollectionSource,
  CollectionType,
  DialogStatus,
  ExerciseMode,
  GenerationStatus,
  Grade,
  ModeSettingsSource,
  ProgressState,
  Tier,
  TriageVerdict,
} from '@/api/types'

type Tone = 'neutral' | 'known' | 'unsure' | 'unknown'

export const GRADE_LABEL: Record<Grade, string> = {
  again: 'снова',
  hard: 'трудно',
  good: 'хорошо',
  easy: 'легко',
}
export function gradeTone(g: Grade): Tone {
  if (g === 'again') return 'unknown'
  if (g === 'hard') return 'unsure'
  return 'known'
}

/**
 * The Russian name of a trainer, falling back to its wire name.
 *
 * Always go through this rather than indexing [MODE_LABEL] directly: `available` comes from the
 * SERVER's enum, so a mode built after this panel was last deployed is a value the map has never
 * heard of, and a direct lookup renders as blank. That is how `dictation` first appeared here —
 * a row with a checkbox and no name.
 */
export function modeLabel(mode: ExerciseMode): string {
  return MODE_LABEL[mode] ?? mode
}

export const MODE_LABEL: Record<ExerciseMode, string> = {
  multiple_choice: 'выбор',
  word_bank: 'сборка',
  typing: 'ввод',
  listening: 'аудио',
  cloze: 'пропуск',
  scramble: 'предложение',
  dictation: 'диктант',
  pick_correct: 'верное предложение',
  speaking: 'говорение',
  intro: 'интро',
}

export const ACQUISITION_LABEL: Record<Acquisition, string> = {
  new: 'новое',
  learning: 'узнавание',
  graduated: 'выпущено',
}

/** Order matters here: it's the rank used to compare thresholds against a mode's passport floor. */
export const ACQUISITION_RANK: Record<Acquisition, number> = { new: 0, learning: 1, graduated: 2 }

/** Section headings for the «Матрица режимов» screen, grouped by EFFECTIVE opening phase. */
export const PHASE_SECTION_LABEL: Record<Acquisition, string> = {
  new: 'Новые слова',
  learning: 'Узнавание',
  graduated: 'Выпущенные',
}
export const PHASE_SECTION_HINT: Record<Acquisition, string> = {
  new: 'Новые слова — интро на первом знакомстве, без оценки.',
  learning: 'Узнавание — сравнение термина с переводом на первых шагах лестницы.',
  graduated: 'Выпущенные — вся практика; тонкая настройка порогами повторов.',
}

export const MODE_SETTINGS_SOURCE_LABEL: Record<ModeSettingsSource, string> = {
  global: 'общее',
  override: 'своё',
}

export const TRIAGE_VERDICT_LABEL: Record<TriageVerdict, string> = {
  known: 'знаю',
  unknown: 'не знаю',
  unsure: 'не уверен',
}
export function triageVerdictTone(v: TriageVerdict): Tone {
  if (v === 'known') return 'known'
  if (v === 'unsure') return 'unsure'
  return 'unknown'
}

export const STATE_LABEL: Record<ProgressState, string> = {
  new: 'новый',
  learning: 'изучается',
  review: 'повторение',
  relearning: 'переучивание',
  known: 'усвоено',
}
export function stateTone(s: ProgressState): Tone {
  if (s === 'review' || s === 'known') return 'known'
  if (s === 'relearning') return 'unknown'
  if (s === 'learning') return 'unsure'
  return 'neutral'
}

export function statusTone(status: number | null): Tone {
  if (status === null) return 'unknown'
  if (status >= 500) return 'unknown'
  if (status >= 400) return 'unsure'
  if (status >= 200 && status < 300) return 'known'
  return 'neutral'
}

export const COLLECTION_TYPE_LABEL: Record<CollectionType, string> = {
  system: 'системная',
  shared: 'общая',
  custom: 'своя',
}
export const COLLECTION_SOURCE_LABEL: Record<CollectionSource, string> = {
  curated: 'куратор',
  ai: 'ИИ',
  user: 'пользователь',
}

export const DIALOG_STATUS_LABEL: Record<DialogStatus, string> = {
  active: 'активен',
  finished: 'завершён',
  expired: 'истёк',
}

export const GENERATION_STATUS_LABEL: Record<GenerationStatus, string> = {
  pending: 'в очереди',
  running: 'выполняется',
  succeeded: 'успех',
  failed: 'ошибка',
}
export function generationTone(s: GenerationStatus): Tone {
  if (s === 'succeeded') return 'known'
  if (s === 'failed') return 'unknown'
  if (s === 'running') return 'unsure'
  return 'neutral'
}

// ── «Здоровье контента» ──
// What the CONTENT of a term allows. Deliberately worded so it can never be read as «когда
// тренажёр откроется ученику» — that is the ladder's question and lives on its own screen.
export const CONTENT_STATUS_LABEL: Record<ContentStatus, string> = {
  ok: 'собирается',
  blocked: 'не собирается',
  pool_dependent: 'зависит от пула',
}
export function contentStatusTone(s: ContentStatus): Tone {
  if (s === 'ok') return 'known'
  if (s === 'blocked') return 'unknown'
  return 'unsure'
}

/** A short chip for the machine reason. The server also sends a full sentence — show both. */
export const CONTENT_GAP_LABEL: Record<ContentGap, string> = {
  single_word: 'одно слово',
  no_example: 'нет примера',
  example_lacks_term: 'в примере нет термина',
  example_is_term: 'пример = термин',
  no_example_translation: 'нет перевода примера',
  example_too_short: 'пример короткий',
  example_too_long: 'пример длинный',
  too_few_distractors: 'мало дистракторов',
  options_from_pool: 'опции из пула',
}

// `no_variants` больше не приходит: пустой список форм — норма для большинства терминов, а не
// дефект, и флаг на каждой строке навсегда учил не смотреть в эту колонку. Ключ оставлен в типе и
// здесь, потому что старый ответ сервера может его ещё нести.
export const NEEDS_ENRICHMENT_REASON_LABEL: Record<NeedsEnrichmentReason, string> = {
  few_distractors: 'нет запаса дистракторов',
  no_variants: 'нет принимаемых вариантов',
}

export const TIER_LABEL: Record<Tier, string> = {
  free: 'Free',
  premium: 'Premium',
}

export function langLabel(code: string): string {
  const map: Record<string, string> = { en: 'English', ru: 'Русский', de: 'Deutsch', ro: 'Română' }
  return map[code] ?? code.toUpperCase()
}
