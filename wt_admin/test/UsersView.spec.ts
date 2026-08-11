import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import UsersView from '@/views/UsersView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/users', name: 'users', component: UsersView },
      { path: '/users/:id', name: 'user', component: { template: '<div />' } },
    ],
  })
}

describe('UsersView', () => {
  it('renders a paginated table of users from the (mock) API', async () => {
    const router = makeRouter()
    router.push('/users')
    await router.isReady()
    const w = mount(UsersView, { global: { plugins: [router] } })
    await flushPromises()
    // Seed users are listed with their email and tier badge.
    expect(w.text()).toContain('alpha@example.com')
    expect(w.text()).toContain('Premium')
    // The pagination summary renders a total.
    expect(w.text()).toMatch(/из\s+\d/)
  })

  it('navigates to the user detail on row click', async () => {
    const router = makeRouter()
    router.push('/users')
    await router.isReady()
    const push = router.push
    let pushedTo: unknown = null
    router.push = ((to: unknown) => {
      pushedTo = to
      return push.call(router, to as never)
    }) as typeof router.push

    const w = mount(UsersView, { global: { plugins: [router] } })
    await flushPromises()
    await w.find('tbody tr').trigger('click')
    expect(pushedTo).toMatchObject({ name: 'user' })
  })
})
