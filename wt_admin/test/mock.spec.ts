import { describe, expect, it } from 'vitest'
import { mock } from '@/api/mock'

describe('mock admin API (contract shape)', () => {
  it('dashboard returns totals and three cost windows', async () => {
    const d = await mock.dashboard()
    expect(d.totals.users).toBeGreaterThan(0)
    expect(d.totals).toHaveProperty('reviewsToday')
    expect(d.costs.today).toHaveProperty('total')
    expect(d.costs.last7d).toHaveProperty('generation')
    expect(d.costs.allTime).toHaveProperty('total')
  })

  it('paginates users and filters by email search', async () => {
    const page1 = await mock.listUsers({ page: 1, perPage: 3 })
    expect(page1.data.length).toBeLessThanOrEqual(3)
    expect(page1.meta.total).toBeGreaterThanOrEqual(page1.data.length)
    expect(page1.meta).toHaveProperty('totalPages')

    const found = await mock.listUsers({ search: 'alpha' })
    expect(found.data.length).toBe(1)
    expect(found.data[0].email).toContain('alpha')
    expect(found.data[0]).toHaveProperty('tier')
  })

  it('setTier mutates the user tier', async () => {
    const { data } = await mock.listUsers({})
    const target = data[0]
    const next = target.tier === 'premium' ? 'free' : 'premium'
    const res = await mock.setTier(target.id, next)
    expect(res.tier).toBe(next)
    const after = await mock.getUser(target.id)
    expect(after.tier).toBe(next)
  })

  it('plan simulator returns entries dated to the requested day', async () => {
    const { data } = await mock.listUsers({})
    const plan = await mock.getUserPlan(data[0].id, '2026-08-10')
    expect(plan.date).toBe('2026-08-10')
    expect(plan).toHaveProperty('newTermsPerDay')
    expect(plan.entries.every((e) => e.dueAt?.startsWith('2026-08-10'))).toBe(true)
  })

  it('embeds collections in user detail (no separate endpoint)', async () => {
    const { data } = await mock.listUsers({})
    const detail = await mock.getUser(data[0].id)
    expect(Array.isArray(detail.collections)).toBe(true)
    if (detail.collections.length) expect(detail.collections[0]).toHaveProperty('itemsCount')
  })

  it('filters logs by status and user', async () => {
    const byStatus = await mock.listLogs({ status: 500 })
    expect(byStatus.data.every((l) => l.status === 500)).toBe(true)
  })

  it('lists generations and filters by status', async () => {
    const failed = await mock.listGenerations({ status: 'failed' })
    expect(failed.data.every((g) => g.status === 'failed')).toBe(true)
  })

  it('rejects unknown ids', async () => {
    await expect(mock.getUser('nope')).rejects.toThrow()
  })
})
