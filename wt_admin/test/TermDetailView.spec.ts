import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import TermDetailView from '@/views/TermDetailView.vue'
import { mock } from '@/api/mock'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/terms', name: 'terms', component: { template: '<div />' } },
      { path: '/terms/:id', name: 'term', component: TermDetailView, props: true },
      { path: '/collections/:id', name: 'collection', component: { template: '<div />' } },
      // Targets the «Здоровье / тренажёры» section links at. Stubs — this suite is about the term
      // card, but the links have to RESOLVE or router-link throws.
      { path: '/content', name: 'content', component: { template: '<div />' } },
      { path: '/ladder', name: 'ladder', component: { template: '<div />' } },
    ],
  })
}

async function mountTerm(hash = '') {
  // Pick a term the mock seeded with distractors and findings, so the page has something to show.
  const list = await mock.listTerms({ limit: 40 })
  const withExtras = await Promise.all(list.data.map((t) => mock.getTerm(t.id)))
  const term = withExtras.find((t) => t.examples.some((e) => e.distractors.length > 0)) ?? withExtras[0]

  const router = makeRouter()
  router.push(`/terms/${term.id}${hash}`)
  await router.isReady()
  const w = mount(TermDetailView, { props: { id: term.id }, global: { plugins: [router] } })
  await flushPromises()
  return { w, term }
}

describe('TermDetailView', () => {
  it('shows the pinned example first, with its distractors', async () => {
    const { w, term } = await mountTerm()

    expect(w.text()).toContain(term.text)
    expect(w.text()).toContain('закреплён')
    expect(w.text()).toContain('Дистракторы')
  })

  it('marks the error span inside the distractor sentence', async () => {
    const { w, term } = await mountTerm()

    const distractor = term.examples.flatMap((e) => e.distractors)[0]
    // Highlighting in place is the whole point — otherwise the reader diffs two sentences by eye.
    expect(w.find('mark').text()).toBe(distractor.errorSpan)
  })

  it('states the blast radius before saving an edit, and warns when the example changed', async () => {
    const { w } = await mountTerm()

    await w.findAll('button').find((b) => b.text() === 'Редактировать')!.trigger('click')
    await flushPromises()

    const inputs = w.findAll('input')
    // 0 = term, 1 = translation, 2 = ipa, 3 = example sentence
    await inputs[3].setValue('A completely different sentence.')
    await w.find('form').trigger('submit')
    await flushPromises()

    // ConfirmDialog teleports to <body>, so read the document, not the wrapper.
    const text = document.body.textContent ?? ''
    expect(text).toContain('коллекц')
    expect(text).toContain('прогресс')
    // Changing the example invalidates its distractors — the dialog must say so before the click.
    expect(text).toContain('дистракторы')
  })

  it('says what a deletion keeps and what it destroys', async () => {
    const { w } = await mountTerm()

    await w.findAll('button').find((b) => b.text() === 'Удалить')!.trigger('click')
    await flushPromises()

    const text = document.body.textContent ?? ''
    expect(text).toContain('Строки прогресса будут удалены')
    // The append-only review log is an invariant — the dialog promises it stays.
    expect(text).toContain('журнал ревью')
  })
})

// ── «Здоровье / тренажёры» — the read-only passport section added to the CRUD card ──────────────
//
// Folded by default and MOUNTED only once opened: the passport is a second request, and opening a
// term to fix a translation must not pay for it.

describe('TermDetailView — секция «Здоровье / тренажёры»', () => {
  it('is folded by default and asks the server for nothing', async () => {
    const { w } = await mountTerm()

    expect(w.text()).toContain('Здоровье / тренажёры')
    // The passport's own headings appear only once the section is open.
    expect(w.text()).not.toContain('Что соберётся из этого контента')
    expect(w.findComponent({ name: 'TermContentPassport' }).exists()).toBe(false)
  })

  it('loads the passport lazily, on the first expand', async () => {
    const { w } = await mountTerm()

    await w.find('.health-head').trigger('click')
    await flushPromises()

    expect(w.findComponent({ name: 'TermContentPassport' }).exists()).toBe(true)
    expect(w.text()).toContain('Что соберётся из этого контента')
    expect(w.text()).toContain('зависит от пула')
  })

  it('links into the Контент section and never back at itself', async () => {
    const { w } = await mountTerm()
    await w.find('.health-head').trigger('click')
    await flushPromises()

    const hrefs = w.findAll('a').map((a) => a.attributes('href') ?? '')
    expect(hrefs.some((h) => h.includes('/content?term='))).toBe(true)
    // The passport's own «Открыть карточку термина» is suppressed here — it would point at itself.
    expect(w.findAll('a').some((a) => a.text().includes('Открыть карточку термина'))).toBe(false)
  })

  it('opens the section straight away when the URL carries the #health anchor', async () => {
    const { w } = await mountTerm('#health')
    await flushPromises()

    expect(w.findComponent({ name: 'TermContentPassport' }).exists()).toBe(true)
  })
})
