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

// Contract fields whose VALUE is FOREIGN JSON — a model's own answer, a logged HTTP body or
// header set. The key itself is part of the contract and is camelized like any other; what is
// inside it is not ours to rename.
//
// This used to be a shrug ("JSON blobs are converted too — acceptable, the only such field is log
// bodies and it is not in the contract"), and it was wrong twice over. The log viewer exists to
// show what we actually sent and received, and it was showing `errorSpan` where the wire carried
// `error_span`. Worse, the playground: `parsed_json` is the model's answer, the screen prints it as
// proof, and the sandbox re-parses it looking for the three fields a distractor IS. Renaming the
// keys on the way in made a correct answer look malformed («Не хватает: error_span») and would have
// sent someone rewriting a prompt the model had already obeyed.
//
// Matched on the WIRE key, before conversion.
const OPAQUE_VALUES = new Set(['parsed_json', 'request_body', 'response_body', 'request_headers'])

// Deep-convert every object key snake_case → camelCase. Arrays and primitives pass
// through; the values under OPAQUE_VALUES pass through verbatim.
export function camelizeKeys<T = unknown>(input: unknown): T {
  if (Array.isArray(input)) return input.map((v) => camelizeKeys(v)) as unknown as T
  if (input && typeof input === 'object') {
    const out: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(input as Record<string, unknown>)) {
      out[toCamel(k)] = OPAQUE_VALUES.has(k) ? v : camelizeKeys(v)
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
