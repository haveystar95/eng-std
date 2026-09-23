import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import PlanView from '@/views/PlanView.vue'
import PlansTab from '@/views/user/PlansTab.vue'
import CommandPalette from '@/components/CommandPalette.vue'
import { mock } from '@/api/mock'
import { MOCK_PLAN_CODE, MOCK_PLAN_ID } from '@/api/mock/plan'
import { users } from '@/api/mock/data'
import type { PlanDays } from '@/api/planTypes'
import { chip, tokensBill, voiceBill, voiceName } from '@/utils/planLabels'

// The learner's plan page (ADM-1): one page of sections under a sticky contents, a day filter that
// narrows every section, «JSON» on every block, reached from the learner's «Планы» and from ⌘K.

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/plans/:code', name: 'plan', component: PlanView, props: true },
      { path: '/users', name: 'users', component: { template: '<div />' } },
      { path: '/users/:id/:tab', name: 'user', component: { template: '<div />' } },
    ],
  })
}

async function mountPlan(path = `/plans/${MOCK_PLAN_CODE}`) {
  const router = makeRouter()
  router.push(path)
  await router.isReady()
  const w = mount(PlanView, { props: { code: MOCK_PLAN_CODE }, global: { plugins: [router] }, attachTo: document.body })
  await flushPromises()
  return { w, router }
}

describe('PlanView', () => {
  it('lays the plan out in its sections, the header first, each under the contents', async () => {
    const { w } = await mountPlan()
    for (const title of ['Что не так', 'Обзор', 'Конвейер генерации', 'Урок и голос', 'Прохождение', 'Разговоры', 'Деньги', 'Вызовы API']) {
      expect(w.find('main').text()).toContain(title)
    }
    const contents = w.findAll('.toc-link').map((a) => a.attributes('href'))
    for (const id of ['#header', '#issues', '#days', '#pipeline', '#lesson', '#passage', '#conversations', '#money', '#calls']) {
      expect(contents).toContain(id)
      expect(w.find(id).exists()).toBe(true)
    }
    expect(w.text()).toContain(`${MOCK_PLAN_CODE} · Поход к врачу`)
    expect(w.text()).toContain('день 2 из 3')
    w.unmount()
  })

  it('narrows every section to the chosen day, and keeps the day in the address', async () => {
    const { w, router } = await mountPlan()
    const dayRows = () => w.findAll('#days tbody tr').length
    expect(dayRows()).toBe(3)

    const pill = w.findAll('.filter .pill').find((b) => b.text() === 'день 1')
    await pill?.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.day).toBe('1')
    expect(dayRows()).toBe(1)
    const expected = (await mock.getPlanSection(MOCK_PLAN_CODE, 'days', 1)) as PlanDays
    expect(w.find('#days tbody').text()).toContain(expected.data[0].scene_title_native ?? '')
    expect(w.find('#pipeline').text()).not.toContain('День 2')
    w.unmount()
  })

  it('opens a day straight from ?day=N', async () => {
    const { w } = await mountPlan(`/plans/${MOCK_PLAN_CODE}?day=2`)
    expect(w.findAll('#days tbody tr')).toHaveLength(1)
    expect(w.find('#days tbody').text()).toContain('В аптеке')
    w.unmount()
  })

  it('shows a block as the endpoint answered it under «JSON»', async () => {
    const { w } = await mountPlan()
    const jsonButton = w.findAll('#issues .lnk').find((b) => b.text() === 'JSON')
    await jsonButton?.trigger('click')
    expect(w.find('#issues pre').text()).toContain('"voice_gender_mismatch"')
    expect(w.find('#issues pre').text()).toContain('"place_kind"')
    w.unmount()
  })

  it('reports «план здоров» when no check finds anything', async () => {
    const { w } = await mountPlan(`/plans/${MOCK_PLAN_CODE}?day=3`)
    expect(w.find('#issues').text()).toContain('План здоров')
    w.unmount()
  })

  it('pages the journal of calls and filters it by source', async () => {
    const { w } = await mountPlan()
    const rows = () => w.findAll('#calls tbody tr').length
    const ids = () => w.findAll('#calls tbody tr').map((tr) => tr.text())
    expect(rows()).toBe(50)
    expect(w.find('#calls').text()).toContain('Показано 50 из 60')
    const more = w.findAll('#calls button').find((b) => b.text() === 'Загрузить ещё')
    expect(more).toBeDefined()
    await more?.trigger('click')
    await flushPromises()
    expect(rows()).toBe(60)
    expect(new Set(ids()).size).toBe(60)
    expect(w.findAll('#calls button').some((b) => b.text() === 'Загрузить ещё')).toBe(false)
    const client = w.findAll('#calls .pill').find((b) => b.text() === 'клиент')
    await client?.trigger('click')
    await flushPromises()
    expect(w.findAll('#calls tbody tr').every((tr) => tr.text().includes('клиент'))).toBe(true)
    w.unmount()
  })
})

describe('PlansTab', () => {
  it('lists the learner\'s plans and opens the page of one', async () => {
    const router = makeRouter()
    router.push('/users')
    await router.isReady()
    const w = mount(PlansTab, { props: { userId: users[0].detail.id }, global: { plugins: [router] } })
    await flushPromises()
    expect(w.text()).toContain(MOCK_PLAN_CODE)
    expect(w.text()).toContain('день 2 из 3')
    await w.find('tbody tr').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.params.code).toBe(MOCK_PLAN_CODE)
  })
})

describe('⌘K by a plan code', () => {
  it('turns a 6-letter code into the plan\'s page, whatever its case', async () => {
    const router = makeRouter()
    router.push('/users')
    await router.isReady()
    const w = mount(CommandPalette, { global: { plugins: [router] }, attachTo: document.body })
    ;(w.vm as unknown as { show: () => void }).show()
    await flushPromises()
    await w.find('input').setValue(MOCK_PLAN_CODE.toLowerCase())
    await new Promise((r) => setTimeout(r, 250))
    await flushPromises()
    expect(w.text()).toContain(`${MOCK_PLAN_CODE} · Поход к врачу`)
    expect(w.text()).toContain('план')
    w.unmount()
  })

  it('also takes the full id, and asks nothing for words that cannot be a code', async () => {
    expect((await mock.getPlan(MOCK_PLAN_ID)).code).toBe(MOCK_PLAN_CODE)
    await expect(mock.getPlan('ZZZZZZ')).rejects.toThrow()
  })
})

describe('planLabels', () => {
  it('gives every status one chip, the same everywhere', () => {
    expect(chip('failed')).toEqual({ label: 'failed', tone: 'unknown' })
    expect(chip('lost').tone).toBe('unknown')
    expect(chip('phone')).toEqual({ label: 'телефонный голос', tone: 'unsure' })
    expect(chip('something-new')).toEqual({ label: 'something-new', tone: 'neutral' })
    expect(chip(null).label).toBe('—')
  })

  it('always writes money with its units, and «н/д» for what is not stored', () => {
    expect(tokensBill(9700, 3100, 0.0712)).toBe('9 700 → 3 100 ток. · $0.0712')
    expect(tokensBill(null, null, null)).toBe('н/д ток. · н/д')
    expect(voiceBill(421, 1685, '0.084200')).toBe('421 кр · 1 685 симв · $0.0842')
    expect(voiceBill(null, null, null)).toBe('н/д кр · н/д симв · н/д')
  })

  it('names a voice by the pack\'s role and gender and a short id', () => {
    expect(voiceName({ identity: 'learner:male', short_id: 'TWut…' })).toBe('ученик · male · TWut…')
    expect(voiceName({ identity: null, short_id: 'abcd…' })).toBe('голос вне пакета · abcd…')
    expect(voiceName(null)).toBe('н/д')
  })
})
