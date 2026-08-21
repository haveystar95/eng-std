import { describe, expect, it } from 'vitest'
import { camelizeKeys, mapPage, snakeizeParams } from '@/api/mapping'

describe('camelizeKeys', () => {
  it('deep-converts snake_case keys to camelCase', () => {
    const out = camelizeKeys<{ createdAt: string; user: { userId: string; costUsd: number } }>({
      created_at: '2026-08-09',
      user: { user_id: 'u1', cost_usd: 0.5 },
    })
    expect(out.createdAt).toBe('2026-08-09')
    expect(out.user.userId).toBe('u1')
    expect(out.user.costUsd).toBe(0.5)
  })
  it('walks arrays', () => {
    const out = camelizeKeys<{ items: { itemId: string }[] }>({ items: [{ item_id: 'a' }, { item_id: 'b' }] })
    expect(out.items.map((i) => i.itemId)).toEqual(['a', 'b'])
  })
  // A model's answer is not our contract: the playground prints it as proof and re-parses it
  // looking for `error_span`, so renaming the keys inside makes a correct answer look malformed.
  it('leaves foreign JSON under parsed_json untouched', () => {
    const out = camelizeKeys<{ parsedJson: { items: { distractors: Record<string, string>[] }[] } }>({
      parsed_json: {
        items: [{ text: 'next to', distractors: [{ sentence: 'x', error_span: 'next the', error_type: 'preposition' }] }],
      },
    })
    const row = out.parsedJson.items[0].distractors[0]
    expect(Object.keys(row)).toEqual(['sentence', 'error_span', 'error_type'])
  })
  // Same rule for the log viewer: it exists to show what was actually sent and received.
  it('leaves logged request/response bodies and headers untouched', () => {
    const out = camelizeKeys<{
      requestBody: Record<string, unknown>
      responseBody: Record<string, unknown>
      requestHeaders: Record<string, unknown>
    }>({
      request_body: { max_tokens: 256, response_format: { json_schema: { name: 'cards' } } },
      response_body: { finish_reason: 'stop' },
      request_headers: { content_type: 'application/json' },
    })
    expect(Object.keys(out.requestBody)).toEqual(['max_tokens', 'response_format'])
    expect(Object.keys(out.requestBody.response_format as object)).toEqual(['json_schema'])
    expect(Object.keys(out.responseBody)).toEqual(['finish_reason'])
    expect(Object.keys(out.requestHeaders)).toEqual(['content_type'])
  })
})

describe('snakeizeParams', () => {
  it('converts query keys to snake_case', () => {
    expect(snakeizeParams({ perPage: 25, userId: 'u1', page: 2 })).toEqual({
      per_page: 25,
      user_id: 'u1',
      page: 2,
    })
  })
})

describe('mapPage', () => {
  it('maps the BE envelope and derives totalPages', () => {
    const page = mapPage<{ id: string }>({
      data: [{ id: 'a' }],
      meta: { total: 51, page: 2, per_page: 25 },
    })
    expect(page.data).toEqual([{ id: 'a' }])
    expect(page.meta.perPage).toBe(25)
    expect(page.meta.totalPages).toBe(3) // ceil(51/25)
  })
})
