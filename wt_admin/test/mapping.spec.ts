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
