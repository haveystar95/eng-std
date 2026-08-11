// Resolves runtime API configuration. Precedence:
//   window.__WT_ADMIN__ (Docker/nginx runtime config.js)
//     → import.meta.env.VITE_API_BASE (dev .env)
//       → '/admin/api' (same-origin default, dev proxy)
// Mocks are used when explicitly requested or when no API base is configured, so
// the SPA runs standalone before the backend admin API exists.

const runtime = typeof window !== 'undefined' ? window.__WT_ADMIN__ : undefined

function resolveApiBase(): string {
  const fromRuntime = runtime?.apiBase?.trim()
  if (fromRuntime) return stripTrailingSlash(fromRuntime)
  const fromEnv = import.meta.env.VITE_API_BASE?.trim()
  if (fromEnv) return stripTrailingSlash(fromEnv)
  return '/admin/api'
}

function resolveUseMocks(apiBase: string): boolean {
  if (typeof runtime?.useMocks === 'boolean') return runtime.useMocks
  const env = import.meta.env.VITE_USE_MOCKS?.trim().toLowerCase()
  if (env === '1' || env === 'true') return true
  if (env === '0' || env === 'false') return false
  // No explicit signal: mock only when the API base was never configured.
  return apiBase === '/admin/api' && !import.meta.env.VITE_API_BASE
}

function stripTrailingSlash(s: string): string {
  return s.endsWith('/') ? s.slice(0, -1) : s
}

export const apiBase = resolveApiBase()
export const useMocks = resolveUseMocks(apiBase)
