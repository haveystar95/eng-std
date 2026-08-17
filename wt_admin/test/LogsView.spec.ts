import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import LogsView from '@/views/LogsView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/logs', name: 'logs', component: LogsView },
      { path: '/users/:id/:tab', name: 'user', component: { template: '<div />' } },
      { path: '/collections/:id', name: 'collection', component: { template: '<div />' } },
    ],
  })
}

async function mountAt(path: string) {
  const router = makeRouter()
  router.push(path)
  await router.isReady()
  const w = mount(LogsView, { global: { plugins: [router] } })
  await flushPromises()
  return { w, router }
}

describe('LogsView', () => {
  it('renders derived model, tokens and cost for outbound calls', async () => {
    const { w } = await mountAt('/logs?direction=outbound')

    expect(w.text()).toContain('gpt-4o')
    // The cost column is the point of the screen — it must show money, not just token counts.
    expect(w.text()).toMatch(/\$\s?0\./)
  })

  it('seeds its filters FROM the url, so a shared link reproduces the slice', async () => {
    const { w } = await mountAt('/logs?purpose=images&direction=outbound')

    const purpose = w.findAll('select')[1]
    expect((purpose.element as HTMLSelectElement).value).toBe('images')
    // Pexels rows only — the provider column proves the filter reached the API, not just the form.
    expect(w.text()).toContain('pexels')
  })

  it('writes the filters back INTO the url when applied', async () => {
    const { w, router } = await mountAt('/logs')

    const direction = w.findAll('select')[0]
    await direction.setValue('outbound')
    await w.findAll('button').find((b) => b.text() === 'Применить')!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.direction).toBe('outbound')
  })

  it('expands a row into pretty-printed request and response bodies', async () => {
    const { w } = await mountAt('/logs?direction=outbound')

    await w.find('tbody tr.clickable').trigger('click')
    await flushPromises()

    expect(w.text()).toContain('Тело запроса')
    expect(w.text()).toContain('Тело ответа')
    // Bodies are redacted on write — the panel shows what is stored, secrets already gone.
    expect(w.text()).toContain('Заголовки запроса')
  })
})
