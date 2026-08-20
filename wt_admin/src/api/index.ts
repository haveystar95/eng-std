// The API surface used by the views. Routes to the mock adapter or real HTTP based on
// `useMocks`. One typed facade means views never branch on transport, and the mock and
// the wire always present the same camelCase DTOs (mirrors of openapi-admin.yaml).
import { useMocks } from './config'
import { httpDelete, httpGet, httpGetPage, httpPatch, httpPost, httpPut } from './http'
import { snakeizeParams } from './mapping'
import { mock } from './mock'
import type {
  Admin,
  CollectionContentHealth,
  CollectionDetail,
  CollectionImpact,
  CollectionRow,
  CollectionsQuery,
  ContentHealthSummary,
  CostByPurpose,
  Dashboard,
  ExerciseMode,
  ExerciseModes,
  DialogDetail,
  DialogRow,
  Generation,
  GenerationsQuery,
  LadderCounts,
  LadderEvent,
  LadderLearner,
  LadderPair,
  LadderProgress,
  LadderQuery,
  LoginResponse,
  ModeSettingsMatrix,
  ModeSettingsRowInput,
  Paginated,
  PageQuery,
  RequestLog,
  RequestLogDetail,
  Review,
  ReviewsQuery,
  TermContentPassport,
  TermDetail,
  TermImpact,
  TermPatch,
  TermRow,
  TermsQuery,
  Tier,
  UserDetail,
  UserListQuery,
  UserRow,
} from './types'
import type { DayPlan, LogsQuery } from './types'

export { useMocks }

export const api = {
  // ── Auth ──
  login: (email: string, password: string): Promise<LoginResponse> =>
    useMocks
      ? mock.login(email, password)
      : httpPost('/login', { email, password, device_name: 'wt_admin' }),
  me: (): Promise<Admin> => (useMocks ? mock.me() : httpGet('/me')),
  logout: (): Promise<void> => (useMocks ? Promise.resolve() : httpPost('/logout')),

  // ── Dashboard ──
  dashboard: (): Promise<Dashboard> => (useMocks ? mock.dashboard() : httpGet('/dashboard')),

  // ── Users ──
  listUsers: (q: UserListQuery = {}): Promise<Paginated<UserRow>> =>
    useMocks ? mock.listUsers(q) : httpGetPage('/users', q),
  getUser: (id: string): Promise<UserDetail> => (useMocks ? mock.getUser(id) : httpGet(`/users/${id}`)),
  getUserPlan: (id: string, date: string): Promise<DayPlan> =>
    useMocks ? mock.getUserPlan(id, date) : httpGet(`/users/${id}/plan`, { date }),
  getUserReviews: (id: string, q: ReviewsQuery = {}): Promise<Paginated<Review>> =>
    useMocks ? mock.getUserReviews(id, q) : httpGetPage(`/users/${id}/reviews`, q),
  setTier: (id: string, tier: Tier): Promise<{ id: string; tier: Tier }> =>
    useMocks ? mock.setTier(id, tier) : httpPost(`/users/${id}/tier`, { tier }),

  // ── Acquisition ladder (live observation; read-only, polled) ──
  listLadderLearners: async (): Promise<LadderLearner[]> =>
    useMocks ? mock.listLadderLearners() : (await httpGet<{ data: LadderLearner[] }>('/ladder/learners')).data,
  // Not httpGetPage: this envelope carries `counts` beside `data`/`meta`, so the page is assembled
  // here rather than by the generic mapper.
  getLadderProgress: async (id: string, q: LadderQuery = {}): Promise<LadderProgress> => {
    if (useMocks) return mock.getLadderProgress(id, q)
    const raw = await httpGet<{
      data: LadderPair[]
      meta: { total: number; page: number; perPage: number; nextCursor?: string | null }
      counts: LadderCounts
    }>(`/users/${id}/ladder`, q)
    const perPage = raw.meta?.perPage || 25
    const total = raw.meta?.total || 0
    return {
      data: raw.data ?? [],
      meta: {
        total,
        page: raw.meta?.page || 1,
        perPage,
        totalPages: Math.max(1, Math.ceil(total / perPage)),
        nextCursor: raw.meta?.nextCursor ?? null,
      },
      counts: raw.counts,
    }
  },
  getLadderEvents: async (id: string, limit = 50): Promise<LadderEvent[]> =>
    useMocks
      ? mock.getLadderEvents(id, limit)
      : (await httpGet<{ data: LadderEvent[] }>(`/users/${id}/ladder/events`, { limit })).data,

  // ── Exercise modes (trainer toggles) ──
  // The ORDER of `modes` is the contract: free practice round-robins by index into it, on the
  // server and mirrored on the device — so the screen sends the list, never a set.
  getExerciseModes: (): Promise<ExerciseModes> =>
    useMocks ? mock.getExerciseModes() : httpGet('/exercise-modes'),
  setExerciseModes: (modes: ExerciseMode[]): Promise<ExerciseModes> =>
    useMocks ? mock.setExerciseModes(modes) : httpPut('/exercise-modes', { modes }),
  getUserExerciseModes: (id: string): Promise<ExerciseModes> =>
    useMocks ? mock.getUserExerciseModes(id) : httpGet(`/users/${id}/exercise-modes`),
  // null drops the override — the user goes back to the product default, including later changes.
  setUserExerciseModes: (id: string, modes: ExerciseMode[] | null): Promise<ExerciseModes> =>
    useMocks ? mock.setUserExerciseModes(id, modes) : httpPut(`/users/${id}/exercise-modes`, { modes }),

  // ── Mode settings matrix («Матрица режимов»): the full row — on/off, position and the
  // acquisition-ladder threshold — as one editable unit, keyed by (scope, mode). Distinct from the
  // toggles above: this is the newer, dedicated screen's API.
  getModeSettingsMatrix: (): Promise<ModeSettingsMatrix> =>
    useMocks ? mock.getModeSettingsMatrix() : httpGet('/mode-settings'),
  saveModeSettingsRow: (row: ModeSettingsRowInput): Promise<ModeSettingsMatrix> =>
    useMocks ? mock.saveModeSettingsRow(row) : httpPut('/mode-settings', snakeizeParams(row)),
  getUserModeSettingsMatrix: (id: string): Promise<ModeSettingsMatrix> =>
    useMocks ? mock.getUserModeSettingsMatrix(id) : httpGet(`/users/${id}/mode-settings`),
  saveUserModeSettingsRow: (id: string, row: ModeSettingsRowInput): Promise<ModeSettingsMatrix> =>
    useMocks ? mock.saveUserModeSettingsRow(id, row) : httpPut(`/users/${id}/mode-settings`, snakeizeParams(row)),
  resetUserModeSettingsOverride: (id: string, mode: ExerciseMode): Promise<ModeSettingsMatrix> =>
    useMocks
      ? mock.resetUserModeSettingsOverride(id, mode)
      : httpDelete(`/users/${id}/mode-settings/${mode}`),

  // ── Collections ──
  listCollections: (q: CollectionsQuery = {}): Promise<Paginated<CollectionRow>> =>
    useMocks ? mock.listCollections(q) : httpGetPage('/collections', q),
  getCollection: (id: string): Promise<CollectionDetail> =>
    useMocks ? mock.getCollection(id) : httpGet(`/collections/${id}`),
  getCollectionCosts: (id: string): Promise<CostByPurpose> =>
    useMocks ? mock.getCollectionCosts(id) : httpGet(`/collections/${id}/costs`),
  getCollectionImpact: (id: string): Promise<CollectionImpact> =>
    useMocks ? mock.getCollectionImpact(id) : httpGet(`/collections/${id}/impact`),
  updateCollection: (id: string, patch: { title?: string; description?: string | null }): Promise<CollectionDetail> =>
    useMocks ? mock.updateCollection(id, patch) : httpPatch(`/collections/${id}`, patch),
  addCollectionTerm: (id: string, termId: string): Promise<CollectionDetail> =>
    useMocks ? mock.addCollectionTerm(id, termId) : httpPost(`/collections/${id}/terms`, { term_id: termId }),
  removeCollectionTerm: (id: string, termId: string): Promise<CollectionDetail> =>
    useMocks ? mock.removeCollectionTerm(id, termId) : httpDelete(`/collections/${id}/terms/${termId}`),
  // The title has to be typed back — the server checks it too, so this is not a browser-only guard.
  deleteCollection: (id: string, confirmTitle: string): Promise<{ id: string; deleted: boolean }> =>
    useMocks
      ? mock.deleteCollection(id, confirmTitle)
      : httpDelete(`/collections/${id}`, { confirm_title: confirmTitle }),

  // ── Costs ──
  getCosts: (period: 'day' | 'week' | 'month' | 'all' = 'week'): Promise<CostByPurpose> =>
    useMocks ? mock.getCosts(period) : httpGet('/costs', { period }),

  // ── Terms ──
  listTerms: (q: TermsQuery = {}): Promise<Paginated<TermRow>> =>
    useMocks ? mock.listTerms(q) : httpGetPage('/terms', q),
  getTerm: (id: string): Promise<TermDetail> => (useMocks ? mock.getTerm(id) : httpGet(`/terms/${id}`)),
  getTermImpact: (id: string): Promise<TermImpact> =>
    useMocks ? mock.getTermImpact(id) : httpGet(`/terms/${id}/impact`),
  updateTerm: (id: string, patch: TermPatch): Promise<TermDetail> =>
    useMocks ? mock.updateTerm(id, patch) : httpPatch(`/terms/${id}`, snakeizeParams(patch)),
  retireTerm: (id: string): Promise<{ id: string; retired: boolean }> =>
    useMocks ? mock.retireTerm(id) : httpDelete(`/terms/${id}`),

  // ── Content health («Здоровье контента») ──
  // Read-only, and there is deliberately no `runEnrichment`: the догон spends money against a
  // model, so the API hands over a command to paste and the panel never executes it.
  getContentHealthSummary: (): Promise<ContentHealthSummary> =>
    useMocks ? mock.getContentHealthSummary() : httpGet('/content-health/summary'),
  getCollectionContentHealth: (id: string): Promise<CollectionContentHealth> =>
    useMocks ? mock.getCollectionContentHealth(id) : httpGet(`/content-health/collections/${id}`),
  getTermContentPassport: (id: string): Promise<TermContentPassport> =>
    useMocks ? mock.getTermContentPassport(id) : httpGet(`/content-health/terms/${id}`),

  // ── Logs ──
  listLogs: (q: LogsQuery = {}): Promise<Paginated<RequestLog>> =>
    useMocks ? mock.listLogs(q) : httpGetPage('/logs', q),
  getLog: (id: string): Promise<RequestLogDetail> =>
    useMocks ? mock.getLog(id) : httpGet(`/logs/${id}`),

  // ── Practice dialogs (global; filter by userId) ──
  listDialogs: (q: { userId?: string } & PageQuery = {}): Promise<Paginated<DialogRow>> =>
    useMocks ? mock.listDialogs(q) : httpGetPage('/practice-dialogs', q),
  getDialog: (id: string): Promise<DialogDetail> =>
    useMocks ? mock.getDialog(id) : httpGet(`/practice-dialogs/${id}`),

  // ── Generations (global; filter by userId/status) ──
  listGenerations: (q: GenerationsQuery = {}): Promise<Paginated<Generation>> =>
    useMocks ? mock.listGenerations(q) : httpGetPage('/generations', q),
}
