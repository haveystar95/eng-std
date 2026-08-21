// DTOs mirror backend2/openapi/openapi-admin.yaml (the authoritative admin contract),
// camelCased. The BE speaks snake_case; a thin mapping layer (src/api/mapping.ts)
// converts at the HTTP boundary, so views/components stay camelCase. The mock adapter
// emits these same shapes directly. Keep this file in lockstep with the yaml; report
// divergences rather than silently reshaping (see CONTRACT-ASSUMPTIONS.md).

export interface Paginated<T> {
  data: T[]
  meta: PageMeta
}

// BE meta is { total, page, per_page, next_cursor }; totalPages is derived client-side.
// `nextCursor` is the keyset walk: pass it back as `cursor` for the next page, null = the end.
export interface PageMeta {
  page: number
  perPage: number
  total: number
  totalPages: number
  nextCursor: string | null
}

// Every list endpoint accepts either mode. Sending `limit` (with or without `cursor`)
// switches the BE to keyset paging — which is what the infinite scroll uses.
export interface CursorQuery {
  limit?: number
  cursor?: string
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
  /** Distinct users who answered a review in the last 7 days — "using it", not "signed up". */
  activeUsers7d: number
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
  /** The last few outbound calls that failed — "what is broken right now", without going looking. */
  recentFailures: RequestLog[]
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
  | 'pick_correct'
  | 'speaking'
  | 'intro'
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

// ── Acquisition ladder (the live progress screen) ──
/**
 * The ladder dimension, orthogonal to `ProgressState`: `state` says WHEN a word comes back,
 * `acquisition` says WHAT it comes back as. `known` is a state, not an acquisition — but it is the
 * fourth bucket the screen counts, because a self-assessed word is outside the ladder entirely.
 */
export type Acquisition = 'new' | 'learning' | 'graduated'
export type LadderPhase = Acquisition | 'known'

export interface LadderLearner {
  id: string
  name: string
  email: string | null
  /** The later of the last answer and the last intro. Null = has never used the app. */
  lastActivityAt: string | null
  pairsCount: number
}

/** The four phases partition the pairs: total = new + learning + graduated + known. */
export interface LadderCounts {
  total: number
  new: number
  learning: number
  graduated: number
  known: number
  due: number
  /**
   * Rows the trainer will never deal: a «знаю» self-assessment, or a word paused with «Убрать из
   * изучения». Cuts ACROSS the four phase buckets rather than being a fifth one, so it is not part
   * of their sum. It cannot see a word that is merely sitting in a collection untouched — that has
   * no row here at all.
   */
  outOfPool: number
}

export interface LadderReview {
  id: string
  exerciseMode: ExerciseMode | null
  grade: Grade
  isCorrect: boolean | null
  isPractice: boolean
  /** The rung the CARD was dealt at — not the pair's rung now, which this answer may have moved. */
  ladderStep: number | null
  response: string | null
  clientSeq: number
  answeredAt: string | null
}

export interface LadderPair {
  termId: string
  text: string
  translation: string | null
  collections: { id: string; title: string; type: CollectionType }[]
  state: ProgressState
  acquisition: Acquisition
  learningStep: number
  /**
   * The rung, 0–5, derived server-side by the Learning module. **null means OUTSIDE the ladder**
   * (a triage «знаю») and is drawn as a dash — never as rung 0. See LadderDots.
   */
  ladderStep: number | null
  reps: number
  lapses: number
  intervalDays: number
  dueAt: string | null
  lastReviewedAt: string | null
  /** When the intro card was shown. Null = the word has never been introduced. */
  exposedAt: string | null
  lastReview: LadderReview | null
  /**
   * When the learner took the word into study. **null = outside the pool** — «знаю», or paused —
   * and a pair outside the pool is never dealt, whatever rung it stands on. First thing to check
   * when a word «не приходит».
   */
  enrolledAt: string | null
}

export type TriageVerdict = 'known' | 'unknown' | 'unsure'

/**
 * One entry of the live feed: an answer, a word being introduced, or a self-assessed triage
 * verdict. The triage kind exists so a word reaching `known` has an event explaining how it got
 * there — without it the pairs table shows a known word that arrived from nowhere.
 */
export interface LadderEvent {
  id: string
  kind: 'review' | 'exposure' | 'triage'
  termId: string
  termText: string | null
  occurredAt: string | null
  exerciseMode: ExerciseMode | null
  grade: Grade | null
  isCorrect: boolean | null
  isPractice: boolean
  ladderStep: number | null
  response: string | null
  clientSeq: number | null
  /** Set only when `kind` is `triage`. */
  verdict: TriageVerdict | null
}

/** The pairs page carries its counters, so a polled table and its header agree on one moment. */
export interface LadderProgress extends Paginated<LadderPair> {
  counts: LadderCounts
}

// ── Collections ──
export type CollectionType = 'system' | 'shared' | 'custom'
export type CollectionSource = 'curated' | 'ai' | 'user'
export interface CollectionRow {
  id: string
  type: CollectionType
  title: string
  ownerId: string | null
  ownerEmail: string | null
  source: CollectionSource
  itemsCount: number
  createdAt: string | null
  /** Directly-attributable spend (generation + realtime); the card has the full split. */
  costUsd: number
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
export type DistractorErrorType =
  | 'article'
  | 'preposition'
  | 'tense'
  | 'word_order'
  | 'false_friend'
  | 'modal_to'
export interface ExampleDistractor {
  id: string
  sentence: string
  errorType: DistractorErrorType
  /** The exact substring of `sentence` that is wrong — highlighted in place. */
  errorSpan: string
  correction: string
  generatorVersion: string
}
export interface TermExample {
  id: string
  sentence: string
  translation: string | null
  /** Exactly one example per term is pinned (lowest id); only it is ever shown to the learner. */
  isPinned: boolean
  distractors: ExampleDistractor[]
}
export type FindingKind =
  | 'ambiguity'
  | 'language'
  | 'ua_leakage'
  | 'misspelled_or_nonword'
  | 'variant_conflict'
export interface EnrichmentFinding {
  kind: FindingKind
  field: string | null
  detail: string
  generatorVersion: string
  createdAt: string | null
}
export interface TermVariant {
  text: string
  note: string | null
  generatorVersion: string
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
  imageUrl: string | null
  imageAuthor: string | null
  imageAuthorUrl: string | null
  cefr: string | null
  source: string
  createdAt: string | null
  updatedAt: string | null
  translations: TermTranslation[]
  examples: TermExample[]
  collections: { id: string; title: string; type: string }[]
  acceptedVariants: TermVariant[]
  findings: EnrichmentFinding[]
  enrichmentVersion: string | null
  progressCount: number
}

// ── Curation (impact = the blast radius, read before a confirm dialog is drawn) ──
export interface TermImpact {
  termId: string
  text: string
  collectionsCount: number
  usersWithProgress: number
  reviewsCount: number
}
export interface CollectionImpact {
  collectionId: string
  title: string
  type: CollectionType
  ownerId: string | null
  termsCount: number
  subscribers: number
  learnersWithProgress: number
}
export interface TermPatch {
  text?: string
  translation?: string
  ipa?: string | null
  exampleId?: string
  exampleSentence?: string
  exampleTranslation?: string | null
}

// ── Costs ──
export type CallPurpose = 'generation' | 'images' | 'enrichment' | 'realtime' | 'recap' | 'example_regen'
export interface PurposeCost {
  purpose: CallPurpose
  tokensIn: number
  tokensOut: number
  costUsd: number
  calls: number
}
export interface CostByPurpose {
  scopeId: string | null
  period: string | null
  since: string | null
  totalUsd: number
  tokensIn: number
  tokensOut: number
  byPurpose: PurposeCost[]
  /** Caveats the numbers alone would hide (shared terms, log-priced purposes). */
  note: string | null
}

// ── Logs ──
export type LogDirection = 'inbound' | 'outbound'
export interface RequestLog {
  id: string
  direction: LogDirection
  method: string
  host: string | null
  path: string
  service: string | null
  purpose: CallPurpose | null
  collectionId: string | null
  status: number | null
  durationMs: number | null
  userId: string | null
  occurredAt: string | null
  /** Derived from the stored bodies, so the whole history is priced — not just new rows. */
  model: string | null
  tokensIn: number | null
  tokensOut: number | null
  costUsd: number | null
  error: string | null
}
export interface RequestLogDetail extends RequestLog {
  requestBytes: number | null
  responseBytes: number | null
  requestHeaders: Record<string, unknown> | null
  requestBody: Record<string, unknown> | null
  responseBody: Record<string, unknown> | null
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

// ── Mode settings matrix («Матрица режимов») ──
/**
 * Where one row comes from, in the scope that was read: `override` when the requested user owns
 * this mode's row directly, `global` when they inherit it from the product default. A global-scope
 * read (no user) has every row tagged `global` — there is nothing to inherit from there.
 */
export type ModeSettingsSource = 'global' | 'override'

/**
 * One full row of `learning_mode_settings`: on/off, rotation position AND the acquisition-ladder
 * threshold together — unlike `ExerciseModes`, which the older toggles screen edits, and its
 * `admission` array, which the older admission-only write edits. This is the field the BE renamed
 * the DB column to (`min_successful_reviews`, not `min_reps`) — deliberately NOT the same name
 * `/sync` and `/exercise-modes*` still use for the client-facing wire, which is out of scope here.
 */
export interface ModeSettingsRow {
  mode: ExerciseMode
  enabled: boolean
  position: number
  minAcquisition: Acquisition
  minLearningStep: number | null
  minSuccessfulReviews: number | null
  optionsPolicy: 'distant' | 'standard'
  source: ModeSettingsSource
  /**
   * This trainer's constructive minimum — the earliest phase its question can honestly be asked
   * at (QA-20 «честная лестница»/passport наряд). Independent of `minAcquisition`, which is the
   * admin's OWN setting and may (legacy data, a rollback) read below its own floor; the backend
   * rejects a write below it with 422. Server-derived, never written.
   */
  floor: Acquisition
}
/** The row shape a PUT sends — `source`/`floor` are server-derived, never written. */
export type ModeSettingsRowInput = Omit<ModeSettingsRow, 'source' | 'floor'>
export interface ModeSettingsMatrix {
  rows: ModeSettingsRow[]
}

// ── Query params (FE side; mapped to snake_case + page/per_page at the boundary) ──
export interface PageQuery extends CursorQuery {
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
/** Every field is also a URL query parameter of the ladder screen — the view is shareable. */
export interface LadderQuery extends PageQuery {
  collectionId?: string
  phase?: LadderPhase
  due?: boolean
  /** true = only pool pairs, false = only the ones outside it, undefined = both (the default). */
  inPool?: boolean
}
export interface CollectionsQuery extends PageQuery {
  type?: CollectionType
  search?: string
}
export interface TermsQuery extends PageQuery {
  search?: string
}
/**
 * Every field here is also a URL query parameter of the Logs screen — that is what makes a filtered
 * view shareable: paste the link, see the same slice.
 */
export interface LogsQuery extends PageQuery {
  direction?: LogDirection
  provider?: string
  purpose?: CallPurpose
  status?: number
  statusClass?: '2xx' | '4xx' | '5xx' | 'error'
  userId?: string
  collectionId?: string
  from?: string
  to?: string
  path?: string
  search?: string
}
export interface GenerationsQuery extends PageQuery {
  userId?: string
  status?: GenerationStatus
}

// ── Content health («Здоровье контента») ──
/**
 * What a term's own CONTENT allows a trainer to build — a different question from the acquisition
 * ladder, which says when a trainer opens FOR A LEARNER. A mode can be `ok` here and still never be
 * dealt (the rung is not reached), and it can be `blocked` here for a learner at the top rung.
 *
 * `pool_dependent` is not a hedge: `multiple_choice` builds its wrong options out of OTHER words in
 * the session, so no content of this term decides whether its card assembles.
 */
export type ContentStatus = 'ok' | 'blocked' | 'pool_dependent'

/** The machine reason behind a non-`ok` status. Null exactly when the status is `ok`. */
export type ContentGap =
  | 'single_word'
  | 'no_example'
  | 'example_lacks_term'
  | 'example_is_term'
  | 'no_example_translation'
  | 'example_too_short'
  | 'example_too_long'
  | 'too_few_distractors'
  | 'options_from_pool'

export interface ModeSimulation {
  mode: ExerciseMode
  status: ContentStatus
  reason: ContentGap | null
  /** Already in Russian — the server states the rule in words so the panel cannot re-phrase it. */
  explanation: string
}

/** `few_distractors` / `no_variants` — what a догон would actually fix. */
export type NeedsEnrichmentReason = 'few_distractors' | 'no_variants'

export interface ContentHealthScope {
  scope: 'all' | 'system' | 'user'
  terms: number
  withDistractors: number
  pickCorrectReady: number
  withVariants: number
  withoutExample: number
  needsEnrichment: number
  estimatedTopupUsd: number
  /** `version: null` is «станок не прогонялся» and comes first. */
  enrichmentVersions: { version: string | null; terms: number }[]
}

export interface ContentHealthCollection {
  id: string
  title: string
  type: CollectionType
  terms: number
  withoutExample: number
  pickCorrectReady: number
  needsEnrichment: number
  estimatedTopupUsd: number
}

export interface ContentLabelCount {
  label: string
  count: number
}

export interface ContentHealthSummary {
  /**
   * NOT a partition: a term held by a store collection AND by someone's own list counts in both,
   * and a term in no collection appears only in `all`. The screen says so out loud.
   */
  scopes: {
    all: ContentHealthScope
    system: ContentHealthScope
    user: ContentHealthScope
  }
  collections: ContentHealthCollection[]
  suppressions: { total: number; bySource: ContentLabelCount[] }
  generationRejections: { total: number; byField: ContentLabelCount[] }
  currentGeneratorVersion: string
  minDistractors: number
  costPerTermUsd: number
}

export interface ContentHealthTerm {
  termId: string
  text: string
  translation: string | null
  hasExample: boolean
  /** No pinned example: cured by regenerating it, NOT by the станок — so never billed as a догон. */
  missingExample: boolean
  /** Span-distinct — the number a card can actually deal. */
  usableDistractors: number
  /** Rows on the pinned example; higher than `usableDistractors` when spans collide. */
  rawDistractors: number
  pickCorrectReady: boolean
  variants: number
  enrichmentVersion: string | null
  needsEnrichment: boolean
  needsEnrichmentReasons: NeedsEnrichmentReason[]
}

export interface CollectionContentHealth {
  collectionId: string
  title: string
  type: CollectionType
  /** Most under-stocked first. */
  terms: ContentHealthTerm[]
  needsEnrichment: number
  withoutExample: number
  pickCorrectReady: number
  estimatedTopupUsd: number
  /** A line to paste into a terminal. The panel never runs the станок. */
  topupCommand: string
  minDistractors: number
  costPerTermUsd: number
}

export interface PassportDistractor extends ExampleDistractor {
  /** false = a card would not deal this row; another distractor already breaks the same span. */
  usable: boolean
}

export interface SuppressedSentence {
  sentence: string
  source: 'review' | 'audit'
  createdAt: string | null
}

export interface TermContentPassport {
  termId: string
  text: string
  lang: string
  type: string
  translations: TermTranslation[]
  example: { id: string; sentence: string | null; translation: string | null } | null
  distractors: PassportDistractor[]
  /** Says that `error_type` is the станок's own guess, checked by nothing. */
  errorTypeNote: string
  /** Their own list: a suppression outlives the row — and the example — it was about. */
  suppressed: SuppressedSentence[]
  acceptedVariants: TermVariant[]
  enrichmentVersions: { version: string; createdAt: string | null }[]
  enrichmentVersion: string | null
  findings: EnrichmentFinding[]
  simulation: ModeSimulation[]
  usableDistractors: number
  missingExample: boolean
  needsEnrichment: boolean
  needsEnrichmentReasons: NeedsEnrichmentReason[]
  collections: { id: string; title: string; type: string }[]
  topupCommand: string
  /** Set only when the term is already marked at the current version — a plain run would skip it. */
  topupHint: string | null
  currentGeneratorVersion: string
  minDistractors: number
  costPerTermUsd: number
}

// ── Playground («Песочница») ──
/**
 * A sandbox provider. `available: false` is a normal state with a named cause — the picker greys the
 * option and repeats `reason`, which names the env var that would fix it.
 */
export interface PlaygroundProvider {
  provider: string
  label: string
  /** A closed list: for OpenAI, exactly the models this project already runs. */
  models: string[]
  available: boolean
  reason: string
}

/**
 * `error` and `parseError` are different failures and must not be merged: `error` means the vendor
 * never answered (key, credits, timeout, refusal) and there is nothing to read; `parseError` means
 * it answered fine and simply not in JSON, which is an ordinary result in a prompt sandbox.
 */
export interface PlaygroundResult {
  provider: string
  model: string
  rawText: string
  parsedJson: unknown
  parseError: string | null
  usage: {
    tokensIn: number | null
    tokensOut: number | null
    /** Priced by the backend's one pricing table; null = unpriced model, never free. */
    costUsd: string | null
  }
  latencyMs: number
  error: string | null
}

/** Which check of the real validator decided a row. `duplicate_*` are the dry run's refinements. */
export type ValidationGate =
  | 'kept'
  | 'no_example'
  | 'empty_sentence'
  | 'duplicate'
  | 'duplicate_stored'
  | 'duplicate_suppressed'
  | 'duplicate_in_batch'
  | 'equals_accepted_answer'
  | 'variant_conflict'
  | 'unknown_error_type'
  | 'span_not_found'
  | 'span_inside_accepted_form'
  | 'empty_correction'
  | 'correction_carries_sentence_end'
  | 'equals_example'
  | 'no_op_correction'
  | 'repair_does_not_match_example'
  | 'cap_reached'

export interface PlaygroundValidationRow {
  index: number
  sentence: string
  errorSpan: string
  correction: string
  errorType: string
  verdict: 'KEEP' | 'REJECT'
  gate: ValidationGate
  /** Already in Russian — the backend states the rule in words so the panel cannot re-phrase it. */
  reason: string
  errorTypeDefaulted: boolean
}

export interface PlaygroundValidation {
  items: PlaygroundValidationRow[]
  kept: number
  total: number
  source: 'term' | 'manual'
  termId: string | null
  termText: string
  exampleSentence: string | null
  existingCount: number
  suppressedCount: number
  matchedTermId: string | null
  /** Always false — the sandbox writes nothing, ever. */
  persisted: boolean
}

export interface PlaygroundGenerateInput {
  provider: string
  model: string
  prompt: string
  temperature?: number | null
}

export interface PlaygroundValidateInput {
  items: { sentence: string; error_span: string; correction: string; error_type?: string }[]
  termId?: string | null
  manual?: { term_text: string; example_text: string }
}
