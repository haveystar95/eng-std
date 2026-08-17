import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import DashboardView from '@/views/DashboardView.vue'

// The failures strip links into the logs screen, so the view needs a router.
function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard', name: 'dashboard', component: DashboardView },
      { path: '/logs', name: 'logs', component: { template: '<div />' } },
    ],
  })
}

describe('DashboardView', () => {
  it('loads totals and spend from the (mock) API', async () => {
    const router = makeRouter()
    router.push('/dashboard')
    await router.isReady()
    const w = mount(DashboardView, { global: { plugins: [router] } })
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

  it('shows active users and the recent outbound failures', async () => {
    const router = makeRouter()
    router.push('/dashboard')
    await router.isReady()
    const w = mount(DashboardView, { global: { plugins: [router] } })
    await flushPromises()

    // "Active" is a different number from "registered", and the screen says which is which.
    expect(w.text()).toContain('Активных за 7 дней')
    expect(w.text()).toContain('Последние упавшие исходящие')
  })
})
