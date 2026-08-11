// Domain → display mappings (Russian labels + paper-palette tones).
import type {
  CollectionSource,
  CollectionType,
  DialogStatus,
  ExerciseMode,
  GenerationStatus,
  Grade,
  ProgressState,
  Tier,
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

export const MODE_LABEL: Record<ExerciseMode, string> = {
  multiple_choice: 'выбор',
  word_bank: 'сборка',
  typing: 'ввод',
  listening: 'аудио',
  cloze: 'пропуск',
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

export const TIER_LABEL: Record<Tier, string> = {
  free: 'Free',
  premium: 'Premium',
}

export function langLabel(code: string): string {
  const map: Record<string, string> = { en: 'English', ru: 'Русский', de: 'Deutsch', ro: 'Română' }
  return map[code] ?? code.toUpperCase()
}
