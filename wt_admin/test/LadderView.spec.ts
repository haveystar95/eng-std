import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import LadderDots from '@/components/LadderDots.vue'
import LadderView from '@/views/LadderView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/ladder', name: 'ladder', component: LadderView }],
  })
}

async function mountView() {
  const router = makeRouter()
  router.push('/ladder')
  await router.isReady()
  const w = mount(LadderView, { global: { plugins: [router] } })
  await flushPromises()
  return { w, router }
}

describe('LadderDots', () => {
  // The five dots stand for rungs 0,1,3,4,5 — rung 2 shares rung 1's dot, exactly as in the app.
  it.each([
    [0, 0],
    [1, 1],
    [2, 1],
    [3, 2],
    [4, 3],
    [5, 4],
  ])('lights the dot for rung %i at index %i', (step, index) => {
    const w = mount(LadderDots, { props: { step } })
    const dots = w.findAll('.dot')
    expect(dots).toHaveLength(5)
    expect(dots[index].classes()).toContain('current')
    dots.forEach((d, i) => {
      if (i < index) expect(d.classes()).toContain('passed')
      if (i > index) expect(d.classes()).toContain('ahead')
    })
  })

  it('draws a dash, not dots, for a word outside the ladder', () => {
    const w = mount(LadderDots, { props: { step: null } })
    expect(w.findAll('.dot')).toHaveLength(0)
    expect(w.text()).toContain('знаю')
  })
})

describe('LadderView', () => {
  it('opens on the most recently active learner and shows their pairs and feed', async () => {
    const { w } = await mountView()

    expect(w.text()).toContain('Прогресс обучения')
    // The table renders words with their ladder dots, and the feed has entries.
    expect(w.findAll('tbody tr').length).toBeGreaterThan(0)
    expect(w.findAll('tbody .dot').length).toBeGreaterThan(0)
    expect(w.findAll('.feed .ev').length).toBeGreaterThan(0)
    // The counters are present and add up to the total.
    const tiles = w.findAll('.tile .tile-value').map((t) => Number(t.text()))
    expect(tiles[0]).toBe(tiles[1] + tiles[2] + tiles[3] + tiles[4])
  })

  it('filters by phase when a counter tile is clicked, and puts it in the URL', async () => {
    const { w, router } = await mountView()

    const known = w.findAll('.tile').find((t) => t.text().includes('Знаю'))!
    await known.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.phase).toBe('known')
    // Every remaining row is outside the ladder, so every row draws the dash.
    const rows = w.findAll('tbody tr')
    expect(rows.length).toBeGreaterThan(0)
    rows.forEach((r) => expect(r.find('.known').exists()).toBe(true))

    // Clicking the same tile again clears the filter.
    await known.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.phase).toBeUndefined()
  })

  it('reports how long ago the screen was refreshed, and can be paused', async () => {
    const { w } = await mountView()

    expect(w.text()).toMatch(/обновлено/)
    const toggle = w.find('.live input')
    await toggle.setValue(false)
    expect(w.text()).toContain('обновление на паузе')
  })

  // QA-20: a known word used to appear on the ladder with no event explaining how it got there —
  // the triage verdict is now part of the same feed as answers and intros.
  it('shows a triage verdict in the event feed, distinct from an answer or an intro', async () => {
    const { w } = await mountView()

    const triageEntries = w.findAll('.feed .ev').filter((li) => li.text().includes('триаж'))
    expect(triageEntries.length).toBeGreaterThan(0)
    expect(triageEntries[0].text()).toContain('знаю')
    expect(triageEntries[0].classes()).toContain('known')
  })

  // QA-20: an empty «Срок» read as a hole whether the pair was mid-ladder or released-but-unreviewed
  // — those are two different reasons, and only the released one now says so in words.
  it('labels a released pair with no due date, instead of the same dash as a mid-ladder one', async () => {
    const { w } = await mountView()

    const dueCells = w.findAll('tbody tr').map((r) => r.findAll('td')[4].text())
    expect(dueCells).toContain('к первому повторению')
    // A mid-ladder pair (still on recognition rungs) keeps the plain dash — it has no due date
    // concept at all, which is a different kind of "empty" than "released, awaiting its first review".
    expect(dueCells).toContain('—')
  })
})
