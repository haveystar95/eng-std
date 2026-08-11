// The API surface used by the views. Routes to the mock adapter or real HTTP based on
// `useMocks`. One typed facade means views never branch on transport, and the mock and
// the wire always present the same camelCase DTOs (mirrors of openapi-admin.yaml).
import { useMocks } from './config'
import { httpGet, httpGetPage, httpPost } from './http'
import { mock } from './mock'
import type {
  Admin,
  CollectionDetail,
  CollectionRow,
  CollectionsQuery,
  Dashboard,
  DialogDetail,
  DialogRow,
  Generation,
  GenerationsQuery,
  LoginResponse,
  Paginated,
  PageQuery,
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

  // ── Collections ──
  listCollections: (q: CollectionsQuery = {}): Promise<Paginated<CollectionRow>> =>
    useMocks ? mock.listCollections(q) : httpGetPage('/collections', q),
  getCollection: (id: string): Promise<CollectionDetail> =>
    useMocks ? mock.getCollection(id) : httpGet(`/collections/${id}`),

  // ── Terms ──
  listTerms: (q: TermsQuery = {}): Promise<Paginated<TermRow>> =>
    useMocks ? mock.listTerms(q) : httpGetPage('/terms', q),
  getTerm: (id: string): Promise<TermDetail> => (useMocks ? mock.getTerm(id) : httpGet(`/terms/${id}`)),

  // ── Logs (global; no per-row bodies, no detail endpoint in the contract) ──
  listLogs: (q: LogsQuery = {}): Promise<Paginated<RequestLog>> =>
    useMocks ? mock.listLogs(q) : httpGetPage('/request-logs', q),

  // ── Practice dialogs (global; filter by userId) ──
  listDialogs: (q: { userId?: string } & PageQuery = {}): Promise<Paginated<DialogRow>> =>
    useMocks ? mock.listDialogs(q) : httpGetPage('/practice-dialogs', q),
  getDialog: (id: string): Promise<DialogDetail> =>
    useMocks ? mock.getDialog(id) : httpGet(`/practice-dialogs/${id}`),

  // ── Generations (global; filter by userId/status) ──
  listGenerations: (q: GenerationsQuery = {}): Promise<Paginated<Generation>> =>
    useMocks ? mock.listGenerations(q) : httpGetPage('/generations', q),
}
