// The plan page's words (ADM-1): ONE set of status chips for every status the page shows — the
// plan's, a day's, a lesson's, a card's, a model call's, a sound's, a construction's — and money
// always with its units: a model in tokens there and back · $, a voice in credits · characters · $.
import { count, money } from './format'
import type { Tone } from './labels'

export interface Chip {
  label: string
  tone: Tone
}

const CHIPS: Record<string, Chip> = {
  // plan
  active: { label: 'идёт', tone: 'known' },
  ready: { label: 'готов', tone: 'known' },
  finished: { label: 'завершён', tone: 'neutral' },
  overdue: { label: 'просрочен', tone: 'unsure' },
  unclear: { label: 'неясен', tone: 'unsure' },
  deleted: { label: 'удалён', tone: 'neutral' },
  // day
  locked: { label: 'закрыт замком', tone: 'neutral' },
  open: { label: 'открыт', tone: 'unsure' },
  in_progress: { label: 'в процессе', tone: 'unsure' },
  closed: { label: 'пройден', tone: 'known' },
  // lesson / build
  pending: { label: 'ждёт', tone: 'neutral' },
  building: { label: 'сборка', tone: 'unsure' },
  illustrating: { label: 'фото', tone: 'unsure' },
  failed: { label: 'failed', tone: 'unknown' },
  // card result
  passed: { label: 'верно', tone: 'known' },
  hinted: { label: 'с подсказкой', tone: 'unsure' },
  skipped: { label: 'пропуск', tone: 'neutral' },
  unanswered: { label: 'нет ответа', tone: 'neutral' },
  // model call
  started: { label: 'идёт', tone: 'unsure' },
  completed: { label: 'ответ', tone: 'known' },
  lost: { label: 'lost', tone: 'unknown' },
  // sound
  voiced: { label: 'озвучена', tone: 'known' },
  phone: { label: 'телефонный голос', tone: 'unsure' },
  none: { label: 'нет', tone: 'neutral' },
  bought: { label: 'куплено', tone: 'known' },
  // construction of a talk
  said: { label: 'сказана', tone: 'known' },
  partial: { label: 'частично', tone: 'unsure' },
  // talk
  ended: { label: 'окончен', tone: 'neutral' },
  agent_turn: { label: 'ход роли', tone: 'unsure' },
  your_turn: { label: 'ход ученика', tone: 'unsure' },
  // severity
  error: { label: 'ошибка', tone: 'unknown' },
  warning: { label: 'внимание', tone: 'unsure' },
  // pipeline stages
  done: { label: 'готово', tone: 'known' },
  clean: { label: 'чисто', tone: 'known' },
  warnings: { label: 'предупреждения', tone: 'unsure' },
  opened: { label: 'открыт', tone: 'known' },
  not_opened: { label: 'не открыт', tone: 'neutral' },
  no_scene_photo: { label: 'без фото сцены', tone: 'unsure' },
}

/** The chip of any status on the page; an unknown word is shown as it came, in a quiet chip. */
export function chip(status: string | null | undefined): Chip {
  if (!status) return { label: '—', tone: 'neutral' }
  return CHIPS[status] ?? { label: status, tone: 'neutral' }
}

export const DAY_TYPE_LABEL: Record<string, string> = {
  scene: 'сцена',
  review: 'повторение',
  rehearsal: 'репетиция',
}

export const TALK_TYPE_LABEL: Record<string, string> = {
  day: 'день',
  rehearsal: 'репетиция',
  review: 'повторение',
}

export const SOURCE_LABEL: Record<string, string> = {
  model: 'модель',
  voice: 'голос',
  judge: 'судья слота',
  client: 'клиент',
}

export const LINE_KIND_LABEL: Record<string, string> = {
  partner_line: 'реплика роли',
  learner_line: 'реплика ученика',
  phrase: 'фраза',
  filler: 'фраза с наполнением',
  word: 'слово',
}

/** «ученик · male · TWut…» — the voice as the pack's config names it, and a short id. */
export function voiceName(voice: { identity: string | null; short_id: string | null } | null | undefined): string {
  if (!voice) return 'н/д'
  const [role, gender] = (voice.identity ?? '').split(':')
  const who = role === 'learner' ? 'ученик' : role === 'partner' ? 'собеседник' : 'голос вне пакета'
  return [who, gender, voice.short_id].filter(Boolean).join(' · ')
}

/** A model's bill: «9 700 → 3 100 ток. · $0.0712»; «н/д» for what is not stored. */
export function tokensBill(tokensIn: number | null | undefined, tokensOut: number | null | undefined, usd: number | string | null | undefined): string {
  const t = tokensIn == null && tokensOut == null ? 'н/д ток.' : `${count(tokensIn ?? 0)} → ${count(tokensOut ?? 0)} ток.`
  return `${t} · ${usdOrNa(usd)}`
}

/** A voice's bill: «421 кр · 1 685 симв · $0.0842». */
export function voiceBill(credits: number | null | undefined, characters: number | null | undefined, usd: number | string | null | undefined): string {
  return `${credits == null ? 'н/д' : count(credits)} кр · ${characters == null ? 'н/д' : count(characters)} симв · ${usdOrNa(usd)}`
}

export function usdOrNa(usd: number | string | null | undefined): string {
  if (usd === null || usd === undefined || usd === '') return 'н/д'
  return money(typeof usd === 'string' ? Number(usd) : usd)
}

/** Milliseconds as the page reads them: «850 мс», «41,2 с», «6 мин». */
export function duration(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return 'н/д'
  if (ms < 1000) return `${ms} мс`
  if (ms < 120_000) return `${(ms / 1000).toFixed(1).replace('.', ',')} с`
  return `${Math.round(ms / 60_000)} мин`
}
