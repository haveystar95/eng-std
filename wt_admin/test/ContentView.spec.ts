import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import ContentView from '@/views/ContentView.vue'
import { mock } from '@/api/mock'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/content', name: 'content', component: ContentView },
      // Named targets the screen links to. Stubs: this suite is about the Контент section, not
      // about the pages it points at — but the links must RESOLVE, or router-link throws.
      { path: '/ladder', name: 'ladder', component: { template: '<div/>' } },
      { path: '/terms/:id', name: 'term', component: { template: '<div/>' } },
    ],
  })
}

async function mountAt(query: string) {
  const router = makeRouter()
  router.push(`/content${query}`)
  await router.isReady()
  const w = mount(ContentView, { global: { plugins: [router] } })
  await flushPromises()
  return { w, router }
}

async function someCollectionId(): Promise<string> {
  const summary = await mock.getContentHealthSummary()
  // A collection that actually holds terms — an empty one renders an empty table and asserts nothing.
  return summary.collections.find((c) => c.terms > 0)!.id
}

describe('ContentView — сводка', () => {
  it('shows the coverage figures and the collections table', async () => {
    const { w } = await mountAt('')
    const summary = await mock.getContentHealthSummary()

    expect(w.text()).toContain('Здоровье контента')
    expect(w.text()).toContain('Терминов всего')
    expect(w.text()).toContain('Требуют станка')
    expect(w.text()).toContain(String(summary.scopes.all.terms))
    // The three slices are shown AND explained as non-additive — the sentence is the point.
    expect(w.text()).toContain('Не разбиение и не складывается')
  })

  it('badges the collections that need the станок', async () => {
    const { w } = await mountAt('')
    const summary = await mock.getContentHealthSummary()
    const needy = summary.collections.filter((c) => c.needsEnrichment > 0)

    if (needy.length > 0) {
      expect(w.text()).toContain(`${needy[0].needsEnrichment} терминов требуют станка`)
    }
  })

  it('drills into a collection through the query string', async () => {
    const { w, router } = await mountAt('')
    const id = await someCollectionId()

    const row = w.findAll('tbody tr').find((r) => r.text().includes('терминов требуют станка') || r.html().includes(id))
    await row!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.collection).toBeTruthy()
    expect(w.text()).toContain('Догон всей коллекции')
  })
})

describe('ContentView — коллекция', () => {
  it('lists terms most under-stocked first, with the usable distractor count', async () => {
    const id = await someCollectionId()
    const { w } = await mountAt(`?collection=${id}`)
    const health = await mock.getCollectionContentHealth(id)

    const texts = w.findAll('tbody tr').map((r) => r.find('.term').text())
    expect(texts).toEqual(health.terms.map((t) => t.text))

    // The first row is the worst one: no example, or the fewest usable distractors.
    const first = health.terms[0]
    expect(first.hasExample === false || first.usableDistractors <= health.terms[health.terms.length - 1].usableDistractors).toBe(true)

    expect(w.text()).toContain('php artisan enrich:backfill')
    expect(w.text()).toContain('--topup=' + health.minDistractors)
  })

  it('opens a term passport from a row', async () => {
    const id = await someCollectionId()
    const { w, router } = await mountAt(`?collection=${id}`)

    await w.findAll('tbody tr')[0].trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.term).toBeTruthy()
    expect(router.currentRoute.value.query.collection).toBe(id)
    expect(w.text()).toContain('Что соберётся из этого контента')
  })
})

describe('ContentView — паспорт термина', () => {
  it('simulates every trainer and never mixes in the ladder', async () => {
    const term = (await mock.listTerms({})).data[0]
    const { w } = await mountAt(`?term=${term.id}`)
    const passport = await mock.getTermContentPassport(term.id)

    const rows = w.findAll('.sim li')
    expect(rows).toHaveLength(passport.simulation.length)
    expect(rows.length).toBe(10)

    // The distinction the screen exists to keep: content here, ladder elsewhere.
    expect(w.text()).toContain('что позволяет контент термина')
    expect(w.text()).toContain('Прогресс обучения')

    // multiple_choice is answered honestly rather than with a yes it cannot give.
    expect(w.text()).toContain('зависит от пула')
  })

  it('offers the догон command and never a button that runs it', async () => {
    const term = (await mock.listTerms({})).data[0]
    const { w } = await mountAt(`?term=${term.id}`)

    expect(w.text()).toContain('php artisan enrich:backfill')
    expect(w.text()).toContain('Панель станок не запускает')
    const labels = w.findAll('button').map((b) => b.text())
    expect(labels.some((l) => /запустить|прогнать/i.test(l))).toBe(false)
  })

  it('links back to the CRUD term card', async () => {
    const term = (await mock.listTerms({})).data[0]
    const { w } = await mountAt(`?term=${term.id}`)

    const link = w.findAll('a').find((a) => a.text().includes('Открыть карточку термина'))
    expect(link).toBeTruthy()
    expect(link!.attributes('href')).toContain(term.id)
  })

  it('keeps «нет примера» out of the станок bill', async () => {
    // The mock's terms all carry an example, so this asserts the wording that separates the two
    // cures rather than a state the seed cannot produce.
    const term = (await mock.listTerms({})).data[0]
    const passport = await mock.getTermContentPassport(term.id)
    expect(passport.missingExample).toBe(false)

    const { w } = await mountAt(`?term=${term.id}`)
    expect(w.text()).toContain('Нужен станок?')
  })
})
