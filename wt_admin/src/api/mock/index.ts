// Mock adapter: implements the camelCased admin contract over the seed data.
// endpoints route here when `useMocks` is true (no backend configured).
import type {
  Admin,
  CollectionDetail,
  CollectionRow,
  CollectionsQuery,
  Dashboard,
  ExerciseMode,
  ExerciseModes,
  DayPlan,
  DialogDetail,
  DialogRow,
  Generation,
  GenerationsQuery,
  LoginResponse,
  LogsQuery,
  PageQuery,
  Paginated,
  RequestLog,
  Review,
  ReviewsQuery,
  TermDetail,
  TermRow,
  TermsQuery,
  Tier,
  UserDetail,
  UserListQuery,
  UserRow,
} from '../types'
import {
  MOCK_NOW,
  collectionRows,
  collectionTermsById,
  generationRows,
  requestLogs,
  termDetails,
  users,
} from './data'

const DAY = 86_400_000

// Trainer toggles. The mock keeps them in memory for the session so the screen can be driven
// standalone: flip, save, navigate away and back, and the change is still there.
const ALL_MODES: ExerciseMode[] = [
  'multiple_choice',
  'word_bank',
  'typing',
  'listening',
  'cloze',
  'scramble',
  'dictation',
]
let globalModes: ExerciseMode[] = [...ALL_MODES]
const modeOverrides = new Map<string, ExerciseMode[]>()

function modesFor(userId?: string): ExerciseModes {
  const override = userId !== undefined ? (modeOverrides.get(userId) ?? null) : null
  return {
    available: [...ALL_MODES],
    global: [...globalModes],
    override: override ? [...override] : null,
    effective: override ? [...override] : [...globalModes],
    inherits: override === null,
  }
}

function paginate<T>(rows: T[], page = 1, perPage = 25): Paginated<T> {
  const total = rows.length
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const start = (page - 1) * perPage
  return { data: rows.slice(start, start + perPage), meta: { page, perPage, total, totalPages } }
}

function notFound(what: string): never {
  const err = new Error(`${what} не найден`) as Error & { status?: number }
  err.status = 404
  throw err
}

function round(n: number): number {
  return Math.round(n * 1_000_000) / 1_000_000
}
function startOfDay(ms: number): number {
  const d = new Date(ms)
  return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate())
}
function isoDate(ms: number): string {
  return new Date(ms).toISOString().slice(0, 10)
}

function userRow(u: UserDetail): UserRow {
  return {
    id: u.id,
    name: u.name,
    email: u.email,
    tier: u.tier,
    cefr: u.cefr,
    createdAt: u.createdAt,
    collectionsCount: u.collections.length,
    progressCount: u.progress.total,
  }
}

let lastAdmin: Admin = { id: 'mock-admin', email: 'admin@wordtrainer.local', name: 'Den (admin)' }

export const mock = {
  async login(email: string, _password: string): Promise<LoginResponse> {
    lastAdmin = { ...lastAdmin, email: email || lastAdmin.email }
    return { token: 'mock-admin-token', admin: lastAdmin }
  },
  async me(): Promise<Admin> {
    return lastAdmin
  },

  async dashboard(): Promise<Dashboard> {
    const totals = {
      users: users.length,
      collections: collectionRows.length,
      terms: termDetails.length,
      reviewsToday: users.reduce((n, u) => n + u.detail.reviewsToday, 0),
      reviews7d: users.reduce((n, u) => n + Math.min(u.detail.reviewsTotal, 300), 0),
    }
    const breakdown = (scale: number) => {
      let generation = 0
      let practice = 0
      let enrichment = 0
      let exampleRegen = 0
      for (const u of users) {
        generation += u.detail.costs.generation.costUsd * scale
        practice += u.detail.costs.practice.costUsd * scale
        exampleRegen += u.detail.costs.exampleRegen.costUsd * scale
        enrichment += u.detail.costs.exampleRegen.costUsd * 0.1 * scale
      }
      return {
        generation: round(generation),
        practice: round(practice),
        enrichment: round(enrichment),
        exampleRegen: round(exampleRegen),
        total: round(generation + practice + enrichment + exampleRegen),
      }
    }
    return { totals, costs: { today: breakdown(0.2), last7d: breakdown(1), allTime: breakdown(1.6) } }
  },

  async listUsers(q: UserListQuery = {}): Promise<Paginated<UserRow>> {
    const search = (q.search ?? '').toLowerCase().trim()
    const rows = users
      .map((u) => userRow(u.detail))
      .filter((u) => !search || (u.email ?? '').toLowerCase().includes(search) || u.name.toLowerCase().includes(search))
    return paginate(rows, q.page, q.perPage)
  },

  async getUser(id: string): Promise<UserDetail> {
    return findUser(id).detail
  },

  async getUserPlan(id: string, date: string): Promise<DayPlan> {
    const u = findUser(id)
    const target = date ? new Date(date + 'T00:00:00.000Z').getTime() : MOCK_NOW
    const dayOffset = Math.round((startOfDay(target) - startOfDay(MOCK_NOW)) / DAY)
    const count = Math.max(2, u.plan.entries.length - Math.abs(dayOffset) * 2)
    const entries = u.plan.entries.slice(0, count).map((it, k) => ({
      ...it,
      dueAt: new Date(startOfDay(target) + (8 + k) * 3600_000).toISOString(),
    }))
    return {
      date: date || isoDate(MOCK_NOW),
      timezone: u.detail.timezone,
      dueCount: entries.filter((e) => !e.isNew).length,
      newIntroduced: entries.filter((e) => e.isNew).length,
      newTermsPerDay: u.detail.dailyGoal,
      entries,
    }
  },

  async getUserReviews(id: string, q: ReviewsQuery = {}): Promise<Paginated<Review>> {
    let rows = findUser(id).reviews
    if (q.from) rows = rows.filter((r) => (r.answeredAt ?? '') >= q.from!)
    if (q.to) rows = rows.filter((r) => (r.answeredAt ?? '') <= q.to! + 'T23:59:59.999Z')
    return paginate(rows, q.page, q.perPage)
  },

  async getExerciseModes(): Promise<ExerciseModes> {
    return modesFor()
  },
  async setExerciseModes(modes: ExerciseMode[]): Promise<ExerciseModes> {
    globalModes = [...modes]
    return modesFor()
  },
  async getUserExerciseModes(id: string): Promise<ExerciseModes> {
    findUser(id) // same 404 as the real API for an unknown user
    return modesFor(id)
  },
  async setUserExerciseModes(id: string, modes: ExerciseMode[] | null): Promise<ExerciseModes> {
    findUser(id)
    // null (or an empty list) drops the override — inherit is the absence of a row, not a copy.
    if (modes === null || modes.length === 0) modeOverrides.delete(id)
    else modeOverrides.set(id, [...modes])
    return modesFor(id)
  },

  async setTier(id: string, tier: Tier): Promise<{ id: string; tier: Tier }> {
    const u = findUser(id)
    u.detail.tier = tier
    return { id, tier }
  },

  async listCollections(q: CollectionsQuery = {}): Promise<Paginated<CollectionRow>> {
    const search = (q.search ?? '').toLowerCase().trim()
    const rows = collectionRows.filter(
      (c) => (!q.type || c.type === q.type) && (!search || c.title.toLowerCase().includes(search)),
    )
    return paginate(rows, q.page, q.perPage)
  },

  async getCollection(id: string): Promise<CollectionDetail> {
    const c = collectionRows.find((x) => x.id === id)
    if (!c) return notFound('Коллекция')
    const terms = collectionTermsById.get(id) ?? []
    return {
      ...c,
      description: 'Демонстрационная коллекция для админ-панели.',
      topic: c.title,
      sourceLang: 'en',
      targetLang: 'ru',
      imageUrl: terms.find((t) => t.imageUrl)?.imageUrl ?? null,
      terms,
    }
  },

  async listTerms(q: TermsQuery = {}): Promise<Paginated<TermRow>> {
    const search = (q.search ?? '').toLowerCase().trim()
    const rows: TermRow[] = termDetails
      .filter(
        (t) =>
          !search ||
          t.text.toLowerCase().includes(search) ||
          t.translations.some((tr) => tr.text.toLowerCase().includes(search)),
      )
      .map((t) => ({
        id: t.id,
        lang: t.lang,
        text: t.text,
        type: t.type as TermRow['type'],
        translation: t.translations.find((tr) => tr.isPrimary)?.text ?? t.translations[0]?.text ?? null,
        createdAt: t.createdAt,
        imageUrl: t.imageUrl,
      }))
    return paginate(rows, q.page, q.perPage)
  },

  async getTerm(id: string): Promise<TermDetail> {
    const t = termDetails.find((x) => x.id === id)
    return t ?? notFound('Термин')
  },

  async listLogs(q: LogsQuery = {}): Promise<Paginated<RequestLog>> {
    let rows = requestLogs
    if (q.userId) rows = rows.filter((l) => l.userId === q.userId)
    if (q.status) rows = rows.filter((l) => l.status === Number(q.status))
    if (q.path) rows = rows.filter((l) => l.path.toLowerCase().includes(q.path!.toLowerCase()))
    return paginate(rows, q.page, q.perPage)
  },

  async listDialogs(q: { userId?: string } & PageQuery = {}): Promise<Paginated<DialogRow>> {
    let all: DialogDetail[] = users.flatMap((u) => u.dialogs)
    if (q.userId) all = all.filter((d) => d.userId === q.userId)
    const rows: DialogRow[] = all.map(stripTranscript)
    return paginate(rows, q.page, q.perPage)
  },

  async getDialog(id: string): Promise<DialogDetail> {
    const d = users.flatMap((u) => u.dialogs).find((x) => x.id === id)
    return d ?? notFound('Диалог')
  },

  async listGenerations(q: GenerationsQuery = {}): Promise<Paginated<Generation>> {
    let rows = generationRows
    if (q.userId) rows = rows.filter((g) => g.userId === q.userId)
    if (q.status) rows = rows.filter((g) => g.status === q.status)
    return paginate(rows, q.page, q.perPage)
  },
}

// ── helpers ──
function findUser(id: string) {
  return users.find((u) => u.detail.id === id) ?? notFound('Пользователь')
}
function stripTranscript(d: DialogDetail): DialogRow {
  const { transcript: _t, summary: _s, ...row } = d
  return row
}
