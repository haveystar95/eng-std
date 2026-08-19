// Deterministic seed data for the mock adapter. Shapes mirror the camelCased admin
// contract (see src/api/types.ts). No time/random dependence beyond a fixed epoch, so
// tests and the standalone SPA are reproducible.
import type {
  Acquisition,
  CollectionRow,
  CollectionSource,
  CollectionTerm,
  CollectionType,
  DialogDetail,
  ExerciseMode,
  Generation,
  GenerationStatus,
  Grade,
  LadderEvent,
  LadderPair,
  PlanEntry,
  ProgressState,
  RequestLog,
  Review,
  TermDetail,
  UserCollection,
  UserDetail,
} from '../types'

export const MOCK_NOW = new Date('2026-08-09T18:30:00.000Z').getTime()

function rng(seed: number): () => number {
  let a = seed
  return () => {
    a |= 0
    a = (a + 0x6d2b79f5) | 0
    let t = Math.imul(a ^ (a >>> 15), 1 | a)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}
const rand = rng(1337)
const pick = <T>(xs: T[]): T => xs[Math.floor(rand() * xs.length)]
const ulid = (n: number): string => (n.toString(36).toUpperCase() + 'X').padStart(26, '0')
const iso = (msAgo: number): string => new Date(MOCK_NOW - msAgo).toISOString()
const money = (max: number): number => Math.round(rand() * max * 1_000_000) / 1_000_000
// Deterministic placeholder image (stands in for the Pexels image the app stores per
// term/collection; the admin API does not expose image_url yet — mock-only for demo).
const img = (seed: string | number): string => `https://picsum.photos/seed/wt${seed}/240/160`
const DAY = 86_400_000

const EN_TERMS = [
  ['ledger', 'бухгалтерская книга', 'phrase'],
  ['to reconcile', 'сверять', 'phrase'],
  ['overdraft', 'овердрафт', 'word'],
  ['statement', 'выписка', 'word'],
  ['to withdraw', 'снимать (деньги)', 'phrasal_verb'],
  ['deposit', 'вклад', 'word'],
  ['interest rate', 'процентная ставка', 'phrase'],
  ['collateral', 'залог', 'word'],
  ['to endorse', 'индоссировать', 'phrase'],
  ['remittance', 'денежный перевод', 'word'],
  ['thorough', 'тщательный', 'word'],
  ['to grasp', 'уловить, понять', 'phrase'],
  ['brittle', 'хрупкий', 'word'],
  ['to linger', 'задерживаться', 'phrase'],
  ['candid', 'откровенный', 'word'],
  ['makeshift', 'импровизированный', 'word'],
  ['to unwind', 'расслабляться', 'phrasal_verb'],
  ['quaint', 'причудливый', 'word'],
  ['to dwell on', 'зацикливаться на', 'idiom'],
  ['resilient', 'жизнестойкий', 'word'],
] as const

const COLLECTION_TITLES = [
  'At the Bank', 'Idioms of Money', 'Phrasal Verbs I', 'Everyday B2', 'Business Emails',
  'Small Talk', 'Travel Basics', 'Cooking Verbs', 'Tech Interview', 'Doctor Visit',
  'German A1', 'Legal English', 'Weather & Seasons', 'News Headlines', 'C1 Nuance',
]
const STATES: ProgressState[] = ['learning', 'review', 'relearning', 'known']
const MODES: ExerciseMode[] = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze']
const GRADES: Grade[] = ['again', 'hard', 'good', 'easy']
const COLLECTION_TYPES: CollectionType[] = ['system', 'shared', 'custom']
const SOURCES: CollectionSource[] = ['curated', 'ai', 'user']

// Fictional on purpose: these fixtures live in the repository forever, and the panel renders them
// as if they were people. Real names and addresses (including other people's) do not belong in
// version control just because they were convenient while building the screens.
const NAMES: [string, string][] = [
  ['Test Alpha', 'alpha@example.com'],
  ['Test Bravo', 'bravo@example.com'],
  ['Тест Чарли', 'charlie@example.com'],
  ['Test Delta', 'delta@example.com'],
  ['Maria K', 'maria.k@example.com'],
]

function cat(maxCost: number) {
  const count = Math.floor(rand() * 6)
  return { tokensIn: Math.floor(rand() * 8000), tokensOut: Math.floor(rand() * 4000), costUsd: money(maxCost), count }
}

export interface MockUser {
  detail: UserDetail
  plan: { entries: PlanEntry[] }
  reviews: Review[]
  ladder: LadderPair[]
  ladderEvents: LadderEvent[]
  dialogs: DialogDetail[]
  generations: Generation[]
  logIds: string[]
}

/**
 * One word on the ladder, dealt a rung by its index so the seed covers the whole track: the intro,
 * both recognition rungs, assembly, typing, dictation — and a «знаю» with no rung at all, which is
 * the only case that draws the dash instead of dots.
 */
function buildLadderPair(i: number, k: number, collections: UserCollection[]): LadderPair {
  const [text, translation] = EN_TERMS[(i + k) % EN_TERMS.length]
  const isKnown = k % 7 === 6
  const step = isKnown ? null : [0, 1, 2, 3, 4, 5][k % 6]
  const acquisition: Acquisition = step === null ? 'graduated' : step === 0 ? 'new' : step <= 2 ? 'learning' : 'graduated'
  const graduated = acquisition === 'graduated' && !isKnown
  const reps = step === null ? 0 : step === 4 ? 4 : step === 5 ? 7 : graduated ? 1 : 0
  const answeredMsAgo = k * 47_000 + 20_000
  const source = collections[k % collections.length]

  return {
    termId: ulid(20000 + i * 100 + k),
    text,
    translation,
    collections: source ? [{ id: source.id, title: source.title, type: source.type as CollectionType }] : [],
    state: isKnown ? 'known' : graduated ? 'review' : step === 0 ? 'new' : 'learning',
    acquisition,
    learningStep: step !== null && step <= 2 ? step : 0,
    ladderStep: step,
    reps,
    lapses: k % 5 === 0 ? 1 : 0,
    intervalDays: graduated ? [1, 3, 7, 21][k % 4] : 0,
    // A third of the graduated pairs model "released, never reviewed" — off the recognition ladder
    // but not yet scheduled for its first SM-2 review, so `dueAt` is null even though `acquisition`
    // is `graduated`. That is a different reason for null than a mid-ladder pair's, and the screen
    // now says so instead of drawing the same dash for both.
    dueAt: graduated ? (k % 3 === 0 ? null : iso(-(k % 4) * DAY)) : null,
    lastReviewedAt: step !== null && step > 0 ? iso(answeredMsAgo) : null,
    exposedAt: isKnown ? null : iso(answeredMsAgo + 90_000),
    // Out of the pool: a «знаю» self-assessment always, plus one paused word in nine so the
    // «вне пула» filter has something to find in the mock.
    enrolledAt: isKnown || k % 9 === 4 ? null : iso(answeredMsAgo + 600_000),
    lastReview:
      step !== null && step > 0
        ? {
            id: ulid(70000 + i * 100 + k),
            exerciseMode: step <= 2 ? 'multiple_choice' : step === 4 ? 'typing' : step === 5 ? 'dictation' : 'word_bank',
            grade: k % 4 === 3 ? 'again' : 'good',
            isCorrect: k % 4 !== 3,
            isPractice: k % 9 === 8,
            ladderStep: step,
            response: text,
            clientSeq: 100 + k,
            answeredAt: iso(answeredMsAgo),
          }
        : null,
  }
}

/**
 * The feed: every answer that exists, plus the intros, plus a triage verdict for every «знаю»
 * pair — newest first. Without the triage entries a known word appeared on the ladder screen
 * having come from nowhere; this mirrors the real event feed, which merges all three logs.
 */
function buildLadderEvents(pairs: LadderPair[]): LadderEvent[] {
  const events: LadderEvent[] = []
  pairs.forEach((p, idx) => {
    if (p.lastReview) {
      events.push({
        id: p.lastReview.id,
        kind: 'review',
        termId: p.termId,
        termText: p.text,
        occurredAt: p.lastReview.answeredAt,
        exerciseMode: p.lastReview.exerciseMode,
        grade: p.lastReview.grade,
        isCorrect: p.lastReview.isCorrect,
        isPractice: p.lastReview.isPractice,
        ladderStep: p.lastReview.ladderStep,
        response: p.lastReview.response,
        clientSeq: p.lastReview.clientSeq,
        verdict: null,
      })
    }
    if (p.exposedAt) {
      events.push({
        id: `exposure:${p.termId}`,
        kind: 'exposure',
        termId: p.termId,
        termText: p.text,
        occurredAt: p.exposedAt,
        exerciseMode: null,
        grade: null,
        isCorrect: null,
        isPractice: false,
        ladderStep: null,
        response: null,
        clientSeq: null,
        verdict: null,
      })
    }
    if (p.state === 'known') {
      events.push({
        id: `triage:${p.termId}`,
        kind: 'triage',
        termId: p.termId,
        termText: p.text,
        occurredAt: iso(idx * 53_000 + 15_000),
        exerciseMode: null,
        grade: null,
        isCorrect: null,
        isPractice: false,
        ladderStep: null,
        response: null,
        clientSeq: null,
        verdict: 'known',
      })
    }
  })
  return events.sort((a, b) => (b.occurredAt ?? '').localeCompare(a.occurredAt ?? ''))
}

function buildPlanEntry(i: number, k: number): PlanEntry {
  const [text, translation, type] = EN_TERMS[(i + k) % EN_TERMS.length]
  return {
    termId: ulid(20000 + i * 100 + k),
    text,
    translation,
    type,
    state: pick(STATES),
    reps: 1 + Math.floor(rand() * 12),
    intervalDays: pick([0, 1, 1, 3, 7, 14, 30]),
    dueAt: iso((rand() - 0.5) * 2 * DAY),
    exerciseMode: pick(MODES),
    clozeable: rand() < 0.5,
    isNew: rand() < 0.2,
  }
}

function buildUser(i: number): MockUser {
  const [name, email] = NAMES[i]
  const id = ulid(1000 + i)
  const tier: 'free' | 'premium' = i < 2 ? 'premium' : 'free'
  const total = 20 + Math.floor(rand() * 220)
  const learning = Math.floor(total * 0.4)
  const review = Math.floor(total * 0.2)
  const relearning = Math.floor(total * 0.05)
  const known = total - learning - review - relearning
  const collectionsCount = 2 + Math.floor(rand() * 5)

  const collections: UserCollection[] = Array.from({ length: collectionsCount }, (_, k) => ({
    id: ulid(40000 + i * 100 + k),
    title: COLLECTION_TITLES[(i + k) % COLLECTION_TITLES.length],
    type: pick(COLLECTION_TYPES),
    itemsCount: 8 + Math.floor(rand() * 40),
    addedAt: iso((k + 1) * 3 * DAY),
  }))

  const generationCat = cat(0.4)
  const practiceCat = cat(0.3)
  const regenCat = cat(0.05)
  const totalUsd = Math.round((generationCat.costUsd + practiceCat.costUsd + regenCat.costUsd) * 1_000_000) / 1_000_000

  const detail: UserDetail = {
    id,
    name,
    email,
    avatar: null,
    tier,
    cefr: pick(['A2', 'B1', 'B2', 'C1']),
    dailyGoal: pick([10, 15, 20, 30]),
    timezone: pick(['Europe/Kyiv', 'Europe/Warsaw', 'Europe/Bucharest']),
    onboardedAt: i === 3 ? null : iso((30 + i * 3) * DAY - 3600_000),
    createdAt: iso((30 + i * 3) * DAY),
    progress: {
      total,
      learning,
      review,
      relearning,
      known,
      learned: review,
      mastered: known,
      dueToday: Math.floor(rand() * 12),
    },
    reviewsTotal: 40 + Math.floor(rand() * 400),
    reviewsToday: Math.floor(rand() * 40),
    streakDays: Math.floor(rand() * 30),
    costs: { generation: generationCat, practice: practiceCat, exampleRegen: regenCat, totalUsd },
    collections,
  }

  const planEntries = Array.from({ length: 6 + Math.floor(rand() * 8) }, (_, k) => buildPlanEntry(i, k))

  const reviews: Review[] = Array.from({ length: 60 }, (_, k) => {
    const [text] = EN_TERMS[(i + k) % EN_TERMS.length]
    const grade = pick(GRADES)
    return {
      id: ulid(30000 + i * 100 + k),
      termId: ulid(20000 + i * 100 + (k % EN_TERMS.length)),
      termText: text,
      exerciseMode: pick(MODES),
      grade,
      isCorrect: grade !== 'again',
      isPractice: rand() < 0.25,
      clientSeq: k + 1,
      answeredAt: iso(k * 3600_000 + Math.floor(rand() * 1800_000)),
    }
  })

  const dialogCount = tier === 'premium' ? 1 + Math.floor(rand() * 4) : 0
  const dialogs: DialogDetail[] = Array.from({ length: dialogCount }, (_, k) => {
    const lines = 6 + Math.floor(rand() * 16)
    const createdMs = (k + 1) * 2 * DAY
    return {
      id: ulid(50000 + i * 100 + k),
      userId: id,
      collectionId: collections[k % collections.length]?.id ?? ulid(40000 + i * 100),
      status: pick(['finished', 'finished', 'expired']),
      tokensIn: Math.floor(rand() * 400),
      tokensOut: Math.floor(rand() * 600),
      costUsd: money(0.08),
      createdAt: iso(createdMs),
      finishedAt: iso(createdMs - 240_000),
      summary: 'Обсудили открытие счёта и процентную ставку; ученик уверенно оперировал лексикой.',
      transcript: Array.from({ length: lines }, (_, m) => ({
        role: m % 2 === 0 ? 'assistant' : 'user',
        text:
          m % 2 === 0
            ? 'So, tell me — how would you open a savings account here?'
            : 'I would like to make a deposit and ask about the interest rate.',
        ts: Math.floor((MOCK_NOW - createdMs + m * 40_000) / 1000),
      })),
    }
  })

  const genCount = 1 + Math.floor(rand() * 4)
  const generations: Generation[] = Array.from({ length: genCount }, (_, k) => {
    const status: GenerationStatus = pick(['succeeded', 'succeeded', 'succeeded', 'failed'])
    const createdMs = (k + 1) * 4 * DAY
    return {
      id: ulid(55000 + i * 100 + k),
      userId: id,
      prompt: pick(['важные базовые фразы для банка', 'идиомы про деньги', 'фразовые глаголы B2', 'лексика собеседования']),
      status,
      model: 'gpt-4o',
      tokensIn: 1500 + Math.floor(rand() * 3000),
      tokensOut: status === 'failed' ? null : 1000 + Math.floor(rand() * 2500),
      costUsd: status === 'failed' ? null : money(0.05),
      collectionId: status === 'succeeded' ? collections[0]?.id ?? null : null,
      error: status === 'failed' ? 'JSON validation failed after 2 retries' : null,
      createdAt: iso(createdMs),
      finishedAt: iso(createdMs - 33_000),
    }
  })

  const ladder = Array.from({ length: 14 }, (_, k) => buildLadderPair(i, k, collections))

  return {
    detail,
    plan: { entries: planEntries },
    reviews,
    ladder,
    ladderEvents: buildLadderEvents(ladder),
    dialogs,
    generations,
    logIds: [],
  }
}

export const users: MockUser[] = NAMES.map((_, i) => buildUser(i))

// ── Collections (global) ──
export const collectionRows: CollectionRow[] = COLLECTION_TITLES.map((title, i) => ({
  id: ulid(60000 + i),
  type: COLLECTION_TYPES[i % COLLECTION_TYPES.length],
  title,
  ownerId: i % 3 === 0 ? null : users[i % users.length].detail.id,
  ownerEmail: i % 3 === 0 ? null : users[i % users.length].detail.email,
  source: SOURCES[i % SOURCES.length],
  itemsCount: 8 + Math.floor(rand() * 50),
  createdAt: iso((i + 1) * 2 * DAY),
  costUsd: Math.round(rand() * 250_000) / 1e6,
}))
export const collectionTermsById = new Map<string, CollectionTerm[]>(
  collectionRows.map((c, i) => [
    c.id,
    Array.from({ length: Math.min(c.itemsCount, 12) }, (_, k) => {
      const [text, translation] = EN_TERMS[(i + k) % EN_TERMS.length]
      return {
        termId: ulid(70000 + i * 100 + k),
        text,
        translation,
        position: k,
        imageUrl: rand() < 0.75 ? img(`${i}-${k}`) : null,
      }
    }),
  ]),
)

// ── Terms (global) ──
export const termDetails: TermDetail[] = EN_TERMS.map(([text, translation, type], i) => ({
  id: ulid(80000 + i),
  lang: 'en',
  text,
  normalizedText: text.toLowerCase(),
  type,
  pos: type === 'word' ? pick(['noun', 'adj', 'verb']) : null,
  ipa: rand() < 0.6 ? '/ˈledʒər/' : null,
  audioUrl: null,
  imageUrl: i % 4 === 0 ? null : img(`term${i}`),
  imageAuthor: i % 4 === 0 ? null : 'Ann Photographer',
  imageAuthorUrl: i % 4 === 0 ? null : 'https://pexels.com/@ann',
  cefr: pick(['A1', 'A2', 'B1', 'B2']),
  source: pick(['curated', 'ai']),
  createdAt: iso((i + 1) * DAY),
  updatedAt: iso((i + 1) * DAY),
  translations: [
    { lang: 'ru', text: translation, isPrimary: true },
    ...(rand() < 0.4 ? [{ lang: 'ru', text: '(разг.) ' + translation, isPrimary: false }] : []),
  ],
  // Two examples on some terms, so the pinned-first rule is visible in the mock too.
  examples: [
    {
      id: ulid(85000 + i * 2),
      sentence: `The bank sent me a ${text}.`,
      translation: `Банк прислал мне ${translation}.`,
      isPinned: true,
      distractors:
        i % 3 === 0
          ? [
              {
                id: ulid(86000 + i),
                sentence: `The bank sent me a ${text} on Monday morning.`,
                errorType: 'preposition' as const,
                errorSpan: 'on Monday morning',
                correction: 'in the morning on Monday',
                generatorVersion: 'enrich-v1',
              },
            ]
          : [],
    },
    ...(i % 5 === 0
      ? [
          {
            id: ulid(85000 + i * 2 + 1),
            sentence: `Please keep the ${text} safe.`,
            translation: `Пожалуйста, храни ${translation} в надёжном месте.`,
            isPinned: false,
            distractors: [],
          },
        ]
      : []),
  ],
  collections: collectionRows.slice(0, 1 + Math.floor(rand() * 4)).map((c) => ({ id: c.id, title: c.title, type: c.type })),
  acceptedVariants:
    i % 3 === 0
      ? [{ text: text + 's', note: 'множественное число', generatorVersion: 'enrich-v1' }]
      : [],
  findings:
    i % 7 === 0
      ? [
          {
            kind: 'ambiguity' as const,
            field: 'translation',
            detail: `«${translation}» имеет второе значение в другом контексте`,
            generatorVersion: 'enrich-v1',
            createdAt: iso((i + 1) * DAY),
          },
        ]
      : [],
  enrichmentVersion: i % 3 === 0 ? 'enrich-v1' : null,
  progressCount: 1 + Math.floor(rand() * 20),
}))

// ── Logs (global) ──
const PATHS = [
  'api/v1/study/due', 'api/v1/reviews/batch', 'api/v1/generations', 'api/v1/sync',
  'api/v1/collections', 'api/v1/auth/me', 'api/v1/profile', 'api/v1/practice/dialogs',
]
const STATUSES = [200, 200, 200, 201, 204, 401, 422, 500]
const PURPOSES = ['generation', 'images', 'enrichment', 'realtime', 'recap', 'example_regen'] as const
export const requestLogs: RequestLog[] = Array.from({ length: 140 }, (_, i) => {
  const direction: 'inbound' | 'outbound' = rand() < 0.8 ? 'inbound' : 'outbound'
  const user = pick(users)
  const purpose = direction === 'outbound' ? pick([...PURPOSES]) : null
  const tokensIn = purpose && purpose !== 'images' ? 400 + Math.floor(rand() * 3000) : null
  const tokensOut = tokensIn ? Math.floor(tokensIn * (0.2 + rand() * 0.6)) : null
  const model = purpose === 'images' ? null : purpose ? pick(['gpt-4o', 'gpt-4o-mini']) : null
  // Same rates the backend prices with (USD per 1K tokens), so the mock's numbers are believable.
  const rates: Record<string, [number, number]> = { 'gpt-4o': [0.0025, 0.01], 'gpt-4o-mini': [0.00015, 0.0006] }
  const costUsd =
    model && tokensIn && tokensOut
      ? Math.round(((tokensIn / 1000) * rates[model][0] + (tokensOut / 1000) * rates[model][1]) * 1e6) / 1e6
      : null
  return {
    id: ulid(90000 + i),
    direction,
    method: pick(['GET', 'GET', 'POST', 'PUT', 'DELETE']),
    host: direction === 'outbound' ? (purpose === 'images' ? 'api.pexels.com' : 'api.openai.com') : 'localhost',
    path: direction === 'inbound' ? pick(PATHS) : purpose === 'images' ? '/v1/search' : '/v1/chat/completions',
    service: direction === 'outbound' ? (purpose === 'images' ? 'pexels' : 'openai') : null,
    purpose,
    collectionId: purpose && rand() < 0.7 ? collectionRows[i % collectionRows.length].id : null,
    status: pick(STATUSES),
    durationMs: 10 + Math.floor(rand() * 1200),
    userId: direction === 'inbound' ? user.detail.id : null,
    occurredAt: iso(i * 900_000 + Math.floor(rand() * 400_000)),
    model,
    tokensIn,
    tokensOut,
    costUsd,
    error: null,
  }
})
for (const u of users) {
  u.logIds = requestLogs.filter((l) => l.userId === u.detail.id).map((l) => l.id)
}

// ── Generations (global, flattened from users) ──
export const generationRows: Generation[] = users.flatMap((u) => u.generations)
