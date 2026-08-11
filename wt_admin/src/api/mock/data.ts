// Deterministic seed data for the mock adapter. Shapes mirror the camelCased admin
// contract (see src/api/types.ts). No time/random dependence beyond a fixed epoch, so
// tests and the standalone SPA are reproducible.
import type {
  CollectionRow,
  CollectionSource,
  CollectionTerm,
  CollectionType,
  DialogDetail,
  ExerciseMode,
  Generation,
  GenerationStatus,
  Grade,
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
  dialogs: DialogDetail[]
  generations: Generation[]
  logIds: string[]
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

  return { detail, plan: { entries: planEntries }, reviews, dialogs, generations, logIds: [] }
}

export const users: MockUser[] = NAMES.map((_, i) => buildUser(i))

// ── Collections (global) ──
export const collectionRows: CollectionRow[] = COLLECTION_TITLES.map((title, i) => ({
  id: ulid(60000 + i),
  type: COLLECTION_TYPES[i % COLLECTION_TYPES.length],
  title,
  ownerId: i % 3 === 0 ? null : users[i % users.length].detail.id,
  source: SOURCES[i % SOURCES.length],
  itemsCount: 8 + Math.floor(rand() * 50),
  createdAt: iso((i + 1) * 2 * DAY),
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
  source: pick(['curated', 'ai']),
  createdAt: iso((i + 1) * DAY),
  translations: [
    { lang: 'ru', text: translation, isPrimary: true },
    ...(rand() < 0.4 ? [{ lang: 'ru', text: '(разг.) ' + translation, isPrimary: false }] : []),
  ],
  examples: [{ sentence: `The bank sent me a ${text}.`, translation: `Банк прислал мне ${translation}.` }],
  collections: collectionRows.slice(0, 1 + Math.floor(rand() * 4)).map((c) => ({ id: c.id, title: c.title, type: c.type })),
  progressCount: 1 + Math.floor(rand() * 20),
}))

// ── Logs (global) ──
const PATHS = [
  'api/v1/study/due', 'api/v1/reviews/batch', 'api/v1/generations', 'api/v1/sync',
  'api/v1/collections', 'api/v1/auth/me', 'api/v1/profile', 'api/v1/practice/dialogs',
]
const STATUSES = [200, 200, 200, 201, 204, 401, 422, 500]
export const requestLogs: RequestLog[] = Array.from({ length: 140 }, (_, i) => {
  const direction: 'inbound' | 'outbound' = rand() < 0.8 ? 'inbound' : 'outbound'
  const user = pick(users)
  return {
    id: ulid(90000 + i),
    direction,
    method: pick(['GET', 'GET', 'POST', 'PUT', 'DELETE']),
    host: direction === 'outbound' ? 'api.openai.com' : 'localhost',
    path: direction === 'inbound' ? pick(PATHS) : 'v1/chat/completions',
    service: direction === 'outbound' ? 'openai' : null,
    status: pick(STATUSES),
    durationMs: 10 + Math.floor(rand() * 1200),
    userId: direction === 'inbound' ? user.detail.id : null,
    occurredAt: iso(i * 900_000 + Math.floor(rand() * 400_000)),
  }
})
for (const u of users) {
  u.logIds = requestLogs.filter((l) => l.userId === u.detail.id).map((l) => l.id)
}

// ── Generations (global, flattened from users) ──
export const generationRows: Generation[] = users.flatMap((u) => u.generations)
