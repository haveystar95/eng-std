// DTOs mirror backend2/openapi/openapi-admin.yaml (the authoritative admin contract),
// camelCased. The BE speaks snake_case; a thin mapping layer (src/api/mapping.ts)
// converts at the HTTP boundary, so views/components stay camelCase. The mock adapter
// emits these same shapes directly. Keep this file in lockstep with the yaml; report
// divergences rather than silently reshaping (see CONTRACT-ASSUMPTIONS.md).

export interface Paginated<T> {
  data: T[]
  meta: PageMeta
}

// BE meta is { total, page, per_page }; totalPages is derived client-side.
export interface PageMeta {
  page: number
  perPage: number
  total: number
  totalPages: number
}

// ── Auth ──
export interface Admin {
  id: string
  email: string
  name: string
}
export interface LoginResponse {
  token: string
  admin: Admin
}

// ── Dashboard ──
export interface DashboardTotals {
  users: number
  collections: number
  terms: number
  reviewsToday: number
  reviews7d: number
}
export interface CostBreakdown {
  generation: number
  practice: number
  enrichment: number
  exampleRegen: number
  total: number
}
export interface Dashboard {
  totals: DashboardTotals
  costs: {
    today: CostBreakdown
    last7d: CostBreakdown
    allTime: CostBreakdown
  }
}

// ── Users ──
export type Tier = 'free' | 'premium'

export interface UserRow {
  id: string
  name: string
  email: string | null
  tier: Tier
  cefr: string | null
  createdAt: string | null
  collectionsCount: number
  progressCount: number
}

export interface CostCategory {
  tokensIn: number
  tokensOut: number
  costUsd: number
  count: number
}
export interface UserProgress {
  total: number
  learning: number
  review: number
  relearning: number
  known: number
  learned: number
  mastered: number
  dueToday: number
}
export interface UserCollection {
  id: string
  title: string
  type: string
  itemsCount: number
  addedAt: string | null
}
export interface UserDetail {
  id: string
  name: string
  email: string | null
  avatar: string | null
  tier: Tier
  cefr: string | null
  dailyGoal: number
  timezone: string
  onboardedAt: string | null
  createdAt: string | null
  progress: UserProgress
  reviewsTotal: number
  reviewsToday: number
  streakDays: number
  costs: {
    generation: CostCategory
    practice: CostCategory
    exampleRegen: CostCategory
    totalUsd: number
  }
  collections: UserCollection[]
}

export type ProgressState = 'new' | 'learning' | 'review' | 'relearning' | 'known'
export type ExerciseMode =
  | 'multiple_choice'
  | 'word_bank'
  | 'typing'
  | 'listening'
  | 'cloze'
  | 'scramble'
  | 'dictation'
export type Grade = 'again' | 'hard' | 'good' | 'easy'

export interface PlanEntry {
  termId: string
  text: string
  translation: string | null
  type: string
  state: ProgressState
  reps: number
  intervalDays: number
  dueAt: string | null
  exerciseMode: ExerciseMode
  clozeable: boolean
  isNew: boolean
}
export interface DayPlan {
  date: string
  timezone: string
  dueCount: number
  newIntroduced: number
  newTermsPerDay: number
  entries: PlanEntry[]
}

export interface Review {
  id: string
  termId: string
  termText: string | null
  exerciseMode: ExerciseMode | null
  grade: Grade
  isCorrect: boolean | null
  isPractice: boolean
  clientSeq: number
  answeredAt: string | null
}

// ── Collections ──
export type CollectionType = 'system' | 'shared' | 'custom'
export type CollectionSource = 'curated' | 'ai' | 'user'
export interface CollectionRow {
  id: string
  type: CollectionType
  title: string
  ownerId: string | null
  source: CollectionSource
  itemsCount: number
  createdAt: string | null
}
export interface CollectionTerm {
  termId: string
  text: string
  translation: string | null
  position: number
  imageUrl?: string | null // see TermRow.imageUrl — BE must add `image_url`
}
export interface CollectionDetail extends CollectionRow {
  description: string | null
  topic: string | null
  sourceLang: string
  targetLang: string
  imageUrl?: string | null // collection cover — BE must add `image_url`
  terms: CollectionTerm[]
}

// ── Terms ──
export type TermType = 'word' | 'phrase' | 'idiom' | 'phrasal_verb'
export interface TermRow {
  id: string
  lang: string
  text: string
  type: TermType
  translation: string | null
  createdAt: string | null
  // Not in the admin contract yet (BE must add `image_url`); optional so live stays
  // blank and the mock/offline demo can show it. camelizeKeys maps image_url → imageUrl.
  imageUrl?: string | null
}
export interface TermTranslation {
  lang: string
  text: string
  isPrimary: boolean
}
export interface TermExample {
  sentence: string
  translation: string | null
}
export interface TermDetail {
  id: string
  lang: string
  text: string
  normalizedText: string
  type: string
  pos: string | null
  ipa: string | null
  audioUrl: string | null
  source: string
  createdAt: string | null
  imageUrl?: string | null // BE must add `image_url` — see TermRow.imageUrl
  translations: TermTranslation[]
  examples: TermExample[]
  collections: { id: string; title: string; type: string }[]
  progressCount: number
}

// ── Logs (no request/response bodies, no detail endpoint in the contract) ──
export type LogDirection = 'inbound' | 'outbound'
export interface RequestLog {
  id: string
  direction: LogDirection
  method: string
  host: string | null
  path: string
  service: string | null
  status: number | null
  durationMs: number | null
  userId: string | null
  occurredAt: string | null
}

// ── Practice dialogs ──
export type DialogStatus = 'active' | 'finished' | 'expired'
export interface DialogRow {
  id: string
  userId: string
  collectionId: string
  status: DialogStatus
  tokensIn: number | null
  tokensOut: number | null
  costUsd: number | null
  createdAt: string | null
  finishedAt: string | null
}
export interface TranscriptLine {
  role: 'user' | 'assistant'
  text: string
  ts: number
}
export interface DialogDetail extends DialogRow {
  summary: string | null
  transcript: TranscriptLine[]
}

// ── Generations ──
export type GenerationStatus = 'pending' | 'running' | 'succeeded' | 'failed'
export interface Generation {
  id: string
  userId: string
  prompt: string
  status: GenerationStatus
  model: string | null
  tokensIn: number | null
  tokensOut: number | null
  costUsd: number | null
  collectionId: string | null
  error: string | null
  createdAt: string | null
  finishedAt: string | null
}

// ── Exercise modes (trainer toggles) ──
/**
 * `override` null means the user INHERITS the product default — a different state from an override
 * that happens to equal it, and what the Inherit/Custom switch is bound to. `available` comes from
 * the server's own enum, so a newly built trainer shows up here the moment it exists (switched off).
 */
export interface ExerciseModes {
  available: ExerciseMode[]
  global: ExerciseMode[]
  override: ExerciseMode[] | null
  effective: ExerciseMode[]
  inherits: boolean
}

// ── Query params (FE side; mapped to snake_case + page/per_page at the boundary) ──
export interface PageQuery {
  page?: number
  perPage?: number
}
export interface UserListQuery extends PageQuery {
  search?: string
}
export interface ReviewsQuery extends PageQuery {
  from?: string
  to?: string
}
export interface CollectionsQuery extends PageQuery {
  type?: CollectionType
  search?: string
}
export interface TermsQuery extends PageQuery {
  search?: string
}
export interface LogsQuery extends PageQuery {
  userId?: string
  status?: number
  path?: string
}
export interface GenerationsQuery extends PageQuery {
  userId?: string
  status?: GenerationStatus
}
