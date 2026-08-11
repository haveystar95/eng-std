import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '@/components/Pagination.vue'

const meta = (page: number, totalPages: number) => ({ page, perPage: 25, total: totalPages * 25, totalPages })

describe('Pagination', () => {
  it('shows the row range', () => {
    const w = mount(Pagination, { props: { meta: { page: 2, perPage: 25, total: 137, totalPages: 6 } } })
    expect(w.text()).toContain('26–50')
    expect(w.text()).toContain('137')
    expect(w.text()).toContain('2 / 6')
  })

  it('disables prev on the first page and next on the last', () => {
    const first = mount(Pagination, { props: { meta: meta(1, 3) } })
    const last = mount(Pagination, { props: { meta: meta(3, 3) } })
    expect(first.findAll('button')[0].attributes('disabled')).toBeDefined()
    expect(last.findAll('button')[1].attributes('disabled')).toBeDefined()
  })

  it('emits change when navigating', async () => {
    const w = mount(Pagination, { props: { meta: meta(2, 5) } })
    await w.findAll('button')[1].trigger('click') // next
    expect(w.emitted('change')?.[0]).toEqual([3])
  })
})
