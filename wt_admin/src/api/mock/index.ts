// Mock adapter: implements the camelCased admin contract over the seed data.
// endpoints route here when `useMocks` is true (no backend configured).
import type {
  Acquisition,
  Admin,
  CallPurpose,
  CollectionDetail,
  CollectionImpact,
  CollectionRow,
  CollectionsQuery,
  CostByPurpose,
  Dashboard,
  ExerciseMode,
  ExerciseModes,
  DayPlan,
  DialogDetail,
  DialogRow,
  Generation,
  GenerationsQuery,
  LadderEvent,
  LadderLearner,
  LadderProgress,
  LadderQuery,
  LoginResponse,
  LogsQuery,
  ModeSettingsMatrix,
  ModeSettingsRow,
  ModeSettingsRowInput,
  PageQuery,
  Paginated,
  RequestLog,
  RequestLogDetail,
  Review,
  ReviewsQuery,
  TermDetail,
  TermImpact,
  TermPatch,
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

// «Матрица режимов»: the full row per mode. Every mode this build knows gets one, mirroring the
// shipped matrix's rungs — same data the toggles mock above is deliberately NOT kept in sync with,
// since the two screens edit different rows of the same real table and this file has no database
// to keep them honest against.
type ModeSettingsRowData = Omit<ModeSettingsRow, 'mode' | 'source'>
const MODE_SETTINGS_ORDER: ExerciseMode[] = [
  'multiple_choice',
  'word_bank',
  'cloze',
  'scramble',
  'typing',
  'listening',
  'speaking',
  'dictation',
  'pick_correct',
  'intro',
]
// Mirrors the backend's ModePassport::floorFor — the constructive minimum phase a trainer's
// question can honestly be asked at. Kept here, not derived, for the same reason the mock has no
// database: this file has nothing to read the real passport from.
const MODE_FLOOR: Record<ExerciseMode, Acquisition> = {
  intro: 'new',
  multiple_choice: 'learning',
  word_bank: 'graduated',
  cloze: 'graduated',
  scramble: 'graduated',
  pick_correct: 'graduated',
  typing: 'graduated',
  listening: 'graduated',
  dictation: 'graduated',
  speaking: 'graduated',
}
const SHIPPED_MODE_SETTINGS: Record<ExerciseMode, ModeSettingsRowData> = {
  intro: { enabled: false, position: 9, minAcquisition: 'new', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.intro },
  multiple_choice: { enabled: true, position: 0, minAcquisition: 'learning', minLearningStep: 1, minSuccessfulReviews: null, optionsPolicy: 'distant', floor: MODE_FLOOR.multiple_choice },
  word_bank: { enabled: true, position: 1, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.word_bank },
  cloze: { enabled: true, position: 2, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.cloze },
  scramble: { enabled: true, position: 3, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.scramble },
  typing: { enabled: true, position: 4, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: 4, optionsPolicy: 'standard', floor: MODE_FLOOR.typing },
  listening: { enabled: true, position: 5, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: 4, optionsPolicy: 'standard', floor: MODE_FLOOR.listening },
  speaking: { enabled: false, position: 6, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.speaking },
  dictation: { enabled: false, position: 7, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: 20, optionsPolicy: 'standard', floor: MODE_FLOOR.dictation },
  pick_correct: { enabled: false, position: 8, minAcquisition: 'graduated', minLearningStep: null, minSuccessfulReviews: null, optionsPolicy: 'standard', floor: MODE_FLOOR.pick_correct },
}
const globalModeSettings: Record<ExerciseMode, ModeSettingsRowData> = structuredClone(SHIPPED_MODE_SETTINGS)
const modeSettingsOverrides = new Map<string, Partial<Record<ExerciseMode, ModeSettingsRowData>>>()

function modeSettingsMatrixFor(userId?: string): ModeSettingsMatrix {
  const own = userId !== undefined ? modeSettingsOverrides.get(userId) : undefined
  const rows: ModeSettingsRow[] = MODE_SETTINGS_ORDER.map((mode) => {
    const ownRow = own?.[mode]
    const data = ownRow ?? globalModeSettings[mode]
    return { mode, ...data, source: ownRow ? 'override' : 'global' }
  })
  rows.sort((a, b) => a.position - b.position || a.mode.localeCompare(b.mode))
  return { rows }
}

/**
 * Serves both paging modes, like the real API: `cursor`/`limit` walks the id-DESC keyset, plain
 * `page` keeps the offset behaviour. The mock has to honour the cursor too, or the infinite scroll
 * is only ever exercised against the live backend.
 */
function paginate<T extends { id?: string }>(
  rows: T[],
  q: { page?: number; perPage?: number; limit?: number; cursor?: string } = {},
): Paginated<T> {
  const total = rows.length
  const keyset = q.limit !== undefined || q.cursor !== undefined
  const perPage = keyset ? (q.limit ?? 25) : (q.perPage ?? 25)
  const totalPages = Math.max(1, Math.ceil(total / perPage))

  if (!keyset) {
    const page = q.page ?? 1
    const start = (page - 1) * perPage
    return {
      data: rows.slice(start, start + perPage),
      meta: { page, perPage, total, totalPages, nextCursor: null },
    }
  }

  const startIndex = q.cursor ? rows.findIndex((r) => r.id === q.cursor) + 1 : 0
  const slice = rows.slice(startIndex, startIndex + perPage)
  const last = slice[slice.length - 1]
  const hasMore = startIndex + perPage < total
  return {
    data: slice,
    meta: {
      page: 1,
      perPage,
      total,
      totalPages,
      nextCursor: hasMore && last?.id ? last.id : null,
    },
  }
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
      activeUsers7d: users.filter((u) => u.detail.reviewsToday > 0 || u.detail.streakDays > 0).length,
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
    return {
      totals,
      costs: { today: breakdown(0.2), last7d: breakdown(1), allTime: breakdown(1.6) },
      recentFailures: requestLogs
        .filter((l) => l.direction === 'outbound' && l.status !== null && l.status >= 500)
        .slice(0, 5),
    }
  },

  async listUsers(q: UserListQuery = {}): Promise<Paginated<UserRow>> {
    const search = (q.search ?? '').toLowerCase().trim()
    const rows = users
      .map((u) => userRow(u.detail))
      .filter((u) => !search || (u.email ?? '').toLowerCase().includes(search) || u.name.toLowerCase().includes(search))
    return paginate(rows, q)
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
    return paginate(rows, q)
  },

  async listLadderLearners(): Promise<LadderLearner[]> {
    return users
      .map((u) => ({
        id: u.detail.id,
        name: u.detail.name,
        email: u.detail.email,
        lastActivityAt: u.ladderEvents[0]?.occurredAt ?? null,
        pairsCount: u.ladder.length,
      }))
      .sort((a, b) => (b.lastActivityAt ?? '').localeCompare(a.lastActivityAt ?? ''))
  },

  async getLadderProgress(id: string, q: LadderQuery = {}): Promise<LadderProgress> {
    const all = findUser(id).ladder
    // The counters follow the collection filter but NOT phase/due — same as the real endpoint, so
    // the split above the table stays whole while the table is narrowed.
    const scoped = q.collectionId ? all.filter((p) => p.collections.some((c) => c.id === q.collectionId)) : all

    let rows = scoped
    if (q.phase === 'known') rows = rows.filter((p) => p.state === 'known')
    else if (q.phase) rows = rows.filter((p) => p.acquisition === q.phase && p.state !== 'known')
    if (q.due) rows = rows.filter((p) => p.dueAt !== null && new Date(p.dueAt).getTime() <= MOCK_NOW)
    if (q.inPool !== undefined) rows = rows.filter((p) => (p.enrolledAt !== null) === q.inPool)

    // Offset paging only, like the real endpoint: the order is "most recently answered", which is
    // not a unique key, so the shared id-keyset paginator does not apply here.
    const ordered = [...rows].sort((a, b) => (b.lastReviewedAt ?? '').localeCompare(a.lastReviewedAt ?? ''))
    const perPage = q.perPage ?? 25
    const current = q.page ?? 1
    return {
      data: ordered.slice((current - 1) * perPage, current * perPage),
      meta: {
        page: current,
        perPage,
        total: ordered.length,
        totalPages: Math.max(1, Math.ceil(ordered.length / perPage)),
        nextCursor: null,
      },
      counts: {
        total: scoped.length,
        new: scoped.filter((p) => p.state !== 'known' && p.acquisition === 'new').length,
        learning: scoped.filter((p) => p.state !== 'known' && p.acquisition === 'learning').length,
        graduated: scoped.filter((p) => p.state !== 'known' && p.acquisition === 'graduated').length,
        known: scoped.filter((p) => p.state === 'known').length,
        due: scoped.filter((p) => p.dueAt !== null && new Date(p.dueAt).getTime() <= MOCK_NOW).length,
        outOfPool: scoped.filter((p) => p.enrolledAt === null).length,
      },
    }
  },

  async getLadderEvents(id: string, limit = 50): Promise<LadderEvent[]> {
    return findUser(id).ladderEvents.slice(0, limit)
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

  async getModeSettingsMatrix(): Promise<ModeSettingsMatrix> {
    return modeSettingsMatrixFor()
  },
  async saveModeSettingsRow(row: ModeSettingsRowInput): Promise<ModeSettingsMatrix> {
    const { mode, ...data } = row
    // `floor` is server-derived and never arrives in the write payload — the mock stands in for
    // the backend's ModePassport here, exactly as the real write handler would re-derive it.
    globalModeSettings[mode] = { ...data, floor: MODE_FLOOR[mode] }
    return modeSettingsMatrixFor()
  },
  async getUserModeSettingsMatrix(id: string): Promise<ModeSettingsMatrix> {
    findUser(id)
    return modeSettingsMatrixFor(id)
  },
  async saveUserModeSettingsRow(id: string, row: ModeSettingsRowInput): Promise<ModeSettingsMatrix> {
    findUser(id)
    const { mode, ...data } = row
    const own = modeSettingsOverrides.get(id) ?? {}
    own[mode] = { ...data, floor: MODE_FLOOR[mode] }
    modeSettingsOverrides.set(id, own)
    return modeSettingsMatrixFor(id)
  },
  async resetUserModeSettingsOverride(id: string, mode: ExerciseMode): Promise<ModeSettingsMatrix> {
    findUser(id)
    const own = modeSettingsOverrides.get(id)
    if (own) delete own[mode]
    return modeSettingsMatrixFor(id)
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
    return paginate(rows, q)
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
    return paginate(rows, q)
  },

  async getTerm(id: string): Promise<TermDetail> {
    const t = termDetails.find((x) => x.id === id)
    return t ?? notFound('Термин')
  },

  async listLogs(q: LogsQuery = {}): Promise<Paginated<RequestLog>> {
    let rows = requestLogs
    if (q.direction) rows = rows.filter((l) => l.direction === q.direction)
    if (q.provider) rows = rows.filter((l) => l.service === q.provider)
    if (q.purpose) rows = rows.filter((l) => l.purpose === q.purpose)
    if (q.userId) rows = rows.filter((l) => l.userId === q.userId)
    if (q.collectionId) rows = rows.filter((l) => l.collectionId === q.collectionId)
    if (q.status) rows = rows.filter((l) => l.status === Number(q.status))
    if (q.statusClass) rows = rows.filter((l) => matchesStatusClass(l, q.statusClass!))
    if (q.from) rows = rows.filter((l) => (l.occurredAt ?? '') >= q.from!)
    if (q.to) rows = rows.filter((l) => (l.occurredAt ?? '') <= q.to!)
    if (q.path) rows = rows.filter((l) => l.path.toLowerCase().includes(q.path!.toLowerCase()))
    if (q.search) {
      const needle = q.search.toLowerCase()
      rows = rows.filter((l) => JSON.stringify(mockBodies(l)).toLowerCase().includes(needle))
    }
    return paginate(rows, q)
  },

  async getLog(id: string): Promise<RequestLogDetail> {
    const row = requestLogs.find((l) => l.id === id)
    if (!row) return notFound('Запись лога')
    const bodies = mockBodies(row)
    return {
      ...row,
      requestBytes: JSON.stringify(bodies.request).length,
      responseBytes: JSON.stringify(bodies.response).length,
      // Redacted on write, exactly as the real log stores them.
      requestHeaders: { Authorization: '[REDACTED]', 'Content-Type': 'application/json' },
      requestBody: bodies.request,
      responseBody: bodies.response,
    }
  },

  async getCollectionCosts(id: string): Promise<CostByPurpose> {
    const c = collectionRows.find((x) => x.id === id)
    if (!c) return notFound('Коллекция')
    return costsFromLogs(requestLogs.filter((l) => l.collectionId === id), { scopeId: id })
  },

  async getCosts(period: 'day' | 'week' | 'month' | 'all' = 'week'): Promise<CostByPurpose> {
    const cutoff = { day: 1, week: 7, month: 30, all: 10_000 }[period]
    const since = new Date(MOCK_NOW - cutoff * DAY).toISOString()
    return costsFromLogs(
      requestLogs.filter((l) => (l.occurredAt ?? '') >= since),
      { period, since },
    )
  },

  async getTermImpact(id: string): Promise<TermImpact> {
    const t = termDetails.find((x) => x.id === id)
    if (!t) return notFound('Термин')
    return {
      termId: t.id,
      text: t.text,
      collectionsCount: t.collections.length,
      usersWithProgress: t.progressCount,
      reviewsCount: t.progressCount * 3,
    }
  },

  async updateTerm(id: string, patch: TermPatch): Promise<TermDetail> {
    const t = termDetails.find((x) => x.id === id)
    if (!t) return notFound('Термин')
    if (patch.text !== undefined) t.text = patch.text
    if (patch.ipa !== undefined) t.ipa = patch.ipa
    if (patch.translation !== undefined) {
      const primary = t.translations.find((tr) => tr.isPrimary) ?? t.translations[0]
      if (primary) primary.text = patch.translation
      else t.translations.push({ lang: 'ru', text: patch.translation, isPrimary: true })
    }
    if (patch.exampleId && patch.exampleSentence !== undefined) {
      const ex = t.examples.find((e) => e.id === patch.exampleId)
      if (ex) {
        const changed = ex.sentence !== patch.exampleSentence
        ex.sentence = patch.exampleSentence
        if (patch.exampleTranslation !== undefined) ex.translation = patch.exampleTranslation
        // Mirrors the server: distractors describe the OLD sentence, so they go, and the term is
        // unmarked for the enrichment run.
        if (changed) {
          ex.distractors = []
          t.enrichmentVersion = null
        }
      }
    }
    t.updatedAt = new Date(MOCK_NOW).toISOString()
    return t
  },

  async retireTerm(id: string): Promise<{ id: string; retired: boolean }> {
    const i = termDetails.findIndex((x) => x.id === id)
    if (i === -1) return notFound('Термин')
    termDetails.splice(i, 1)
    return { id, retired: true }
  },

  async getCollectionImpact(id: string): Promise<CollectionImpact> {
    const c = collectionRows.find((x) => x.id === id)
    if (!c) return notFound('Коллекция')
    return {
      collectionId: c.id,
      title: c.title,
      type: c.type,
      ownerId: c.ownerId,
      termsCount: c.itemsCount,
      subscribers: c.type === 'system' ? 4 : 0,
      learnersWithProgress: 2,
    }
  },

  async updateCollection(id: string, patch: { title?: string; description?: string | null }): Promise<CollectionDetail> {
    const c = collectionRows.find((x) => x.id === id)
    if (!c) return notFound('Коллекция')
    if (patch.title !== undefined) c.title = patch.title
    const detail = await mock.getCollection(id)
    return { ...detail, description: patch.description ?? detail.description }
  },

  async addCollectionTerm(id: string, termId: string): Promise<CollectionDetail> {
    const c = collectionRows.find((x) => x.id === id)
    const t = termDetails.find((x) => x.id === termId)
    if (!c || !t) return notFound('Коллекция или термин')
    const terms = collectionTermsById.get(id) ?? []
    if (!terms.some((x) => x.termId === termId)) {
      terms.push({
        termId: t.id,
        text: t.text,
        translation: t.translations[0]?.text ?? null,
        position: terms.length,
        imageUrl: t.imageUrl,
      })
      collectionTermsById.set(id, terms)
      c.itemsCount = terms.length
    }
    return mock.getCollection(id)
  },

  async removeCollectionTerm(id: string, termId: string): Promise<CollectionDetail> {
    const c = collectionRows.find((x) => x.id === id)
    if (!c) return notFound('Коллекция')
    const terms = (collectionTermsById.get(id) ?? []).filter((x) => x.termId !== termId)
    collectionTermsById.set(id, terms)
    c.itemsCount = terms.length
    return mock.getCollection(id)
  },

  async deleteCollection(id: string, confirmTitle: string): Promise<{ id: string; deleted: boolean }> {
    const i = collectionRows.findIndex((x) => x.id === id)
    if (i === -1) return notFound('Коллекция')
    if (collectionRows[i].title !== confirmTitle) {
      const err = new Error('Название не совпадает') as Error & { status?: number }
      err.status = 422
      throw err
    }
    collectionRows.splice(i, 1)
    return { id, deleted: true }
  },

  async listDialogs(q: { userId?: string } & PageQuery = {}): Promise<Paginated<DialogRow>> {
    let all: DialogDetail[] = users.flatMap((u) => u.dialogs)
    if (q.userId) all = all.filter((d) => d.userId === q.userId)
    const rows: DialogRow[] = all.map(stripTranscript)
    return paginate(rows, q)
  },

  async getDialog(id: string): Promise<DialogDetail> {
    const d = users.flatMap((u) => u.dialogs).find((x) => x.id === id)
    return d ?? notFound('Диалог')
  },

  async listGenerations(q: GenerationsQuery = {}): Promise<Paginated<Generation>> {
    let rows = generationRows
    if (q.userId) rows = rows.filter((g) => g.userId === q.userId)
    if (q.status) rows = rows.filter((g) => g.status === q.status)
    return paginate(rows, q)
  },
}

// ── helpers ──
function matchesStatusClass(l: RequestLog, cls: string): boolean {
  if (cls === 'error') return l.error !== null
  if (l.status === null) return false
  if (cls === '2xx') return l.status >= 200 && l.status < 300
  if (cls === '4xx') return l.status >= 400 && l.status < 500
  if (cls === '5xx') return l.status >= 500
  return true
}

/** Plausible bodies for a log row, so the JSON viewer and the body search have something real. */
function mockBodies(l: RequestLog): { request: Record<string, unknown>; response: Record<string, unknown> } {
  if (l.direction === 'inbound') {
    return { request: { path: l.path }, response: { data: { ok: l.status !== null && l.status < 400 } } }
  }
  if (l.purpose === 'images') {
    return { request: { query: 'bank account', per_page: 1 }, response: { photos: [{ id: 42 }] } }
  }
  return {
    request: {
      model: l.model,
      messages: [
        { role: 'system', content: 'You are a vocabulary generator.' },
        { role: 'user', content: 'иду открывать счёт в банке' },
      ],
    },
    response: {
      model: l.model,
      usage: { prompt_tokens: l.tokensIn, completion_tokens: l.tokensOut },
      choices: [{ message: { content: '{"items":[{"text":"account","translation":"счёт"}]}' } }],
    },
  }
}

/** Sum a set of log rows into the by-purpose shape the cost endpoints return. */
function costsFromLogs(
  rows: RequestLog[],
  extra: { scopeId?: string; period?: string; since?: string },
): CostByPurpose {
  const purposes: CallPurpose[] = ['generation', 'images', 'enrichment', 'realtime', 'recap', 'example_regen']
  const byPurpose = purposes.map((purpose) => {
    const mine = rows.filter((l) => l.purpose === purpose)
    return {
      purpose,
      tokensIn: mine.reduce((n, l) => n + (l.tokensIn ?? 0), 0),
      tokensOut: mine.reduce((n, l) => n + (l.tokensOut ?? 0), 0),
      costUsd: round(mine.reduce((n, l) => n + (l.costUsd ?? 0), 0)),
      calls: mine.length,
    }
  })
  return {
    scopeId: extra.scopeId ?? null,
    period: extra.period ?? null,
    since: extra.since ?? null,
    totalUsd: round(byPurpose.reduce((n, p) => n + p.costUsd, 0)),
    tokensIn: byPurpose.reduce((n, p) => n + p.tokensIn, 0),
    tokensOut: byPurpose.reduce((n, p) => n + p.tokensOut, 0),
    byPurpose,
    note: 'enrichment и example_regen считаются по термину, поэтому общий термин учтён в каждой коллекции.',
  }
}

function findUser(id: string) {
  return users.find((u) => u.detail.id === id) ?? notFound('Пользователь')
}
function stripTranscript(d: DialogDetail): DialogRow {
  const { transcript: _t, summary: _s, ...row } = d
  return row
}
