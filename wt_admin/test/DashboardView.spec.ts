import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DashboardView from '@/views/DashboardView.vue'

describe('DashboardView', () => {
  it('loads totals and spend from the (mock) API', async () => {
    const w = mount(DashboardView)
    // Loading state first.
    expect(w.text()).toContain('Обзор')
    await flushPromises()
    // Totals section labels.
    expect(w.text()).toContain('Пользователи')
    expect(w.text()).toContain('Термины')
    // Spend rendered as 4-decimal money.
    expect(w.text()).toMatch(/\$\d+\.\d{4}/)
    // Category breakdown label present.
    expect(w.text()).toContain('Генерация')
  })
})
