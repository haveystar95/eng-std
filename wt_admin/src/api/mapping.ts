// The thin snake_case ↔ camelCase boundary. The BE admin contract is snake_case;
// FE DTOs are camelCase. We convert keys generically (no hand-written field maps) so
// adding a field to the contract needs no change here.
import type { PageMeta, Paginated } from './types'

function toCamel(key: string): string {
  return key.replace(/_([a-z0-9])/g, (_, c: string) => c.toUpperCase())
}
function toSnake(key: string): string {
  return key.replace(/[A-Z]/g, (c) => '_' + c.toLowerCase())
}

// Deep-convert every object key snake_case → camelCase. Arrays and primitives pass
// through; JSON blobs that happen to be nested objects are converted too (acceptable
// for this admin console — the only such field, log bodies, is not in the contract).
export function camelizeKeys<T = unknown>(input: unknown): T {
  if (Array.isArray(input)) return input.map((v) => camelizeKeys(v)) as unknown as T
  if (input && typeof input === 'object') {
    const out: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(input as Record<string, unknown>)) {
      out[toCamel(k)] = camelizeKeys(v)
    }
    return out as T
  }
  return input as T
}

// Convert an FE query object to snake_case params for the wire (page, per_page,
// user_id, …). Undefined/empty entries are dropped by the http layer.
export function snakeizeParams(params?: object): Record<string, unknown> | undefined {
  if (!params) return undefined
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(params)) {
    out[toSnake(k)] = v
  }
  return out
}

// BE paginated envelope { data, meta:{ total, page, per_page } } → FE Paginated with a
// derived totalPages. Applied after camelization (so meta is { total, page, perPage }).
export function mapPage<T>(raw: unknown): Paginated<T> {
  const camel = camelizeKeys<{
    data: T[]
    meta: { total: number; page: number; perPage: number; nextCursor?: string | null }
  }>(raw)
  const perPage = camel.meta?.perPage || 25
  const total = camel.meta?.total || 0
  const meta: PageMeta = {
    total,
    page: camel.meta?.page || 1,
    perPage,
    totalPages: Math.max(1, Math.ceil(total / perPage)),
    // Null (or absent, on an offset read) means there is no next page. That, and not a
    // count-versus-total comparison, is what stops the infinite scroll.
    nextCursor: camel.meta?.nextCursor ?? null,
  }
  return { data: camel.data ?? [], meta }
}
