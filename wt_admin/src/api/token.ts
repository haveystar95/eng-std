// The admin token lives in memory (primary) mirrored to localStorage so a reload
// keeps the session. A single unauthorized-callback lets the axios interceptor tell
// the auth layer to drop the token and route to login, without a circular import.

const STORAGE_KEY = 'wt_admin_token'

let inMemory: string | null = readInitial()
let onUnauthorized: (() => void) | null = null

function readInitial(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

export function getToken(): string | null {
  return inMemory
}

export function setToken(token: string): void {
  inMemory = token
  try {
    localStorage.setItem(STORAGE_KEY, token)
  } catch {
    /* storage may be unavailable; memory copy still works for the session */
  }
}

export function clearToken(): void {
  inMemory = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    /* ignore */
  }
}

export function setUnauthorizedHandler(fn: () => void): void {
  onUnauthorized = fn
}

export function notifyUnauthorized(): void {
  onUnauthorized?.()
}
