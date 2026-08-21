import { beforeEach, describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import PlaygroundView from '@/views/PlaygroundView.vue'
import { findDistractorItems, itemsHint } from '@/utils/distractorItems'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/playground', name: 'playground', component: PlaygroundView }],
  })
}

async function mountView() {
  const router = makeRouter()
  router.push('/playground')
  await router.isReady()
  const w = mount(PlaygroundView, { global: { plugins: [router] } })
  await flushPromises()
  return { w }
}

function button(w: ReturnType<typeof mount>, label: string) {
  const found = w.findAll('button').find((b) => b.text().startsWith(label))
  if (!found) throw new Error(`no button «${label}»`)
  return found
}

beforeEach(() => localStorage.clear())

// ── Finding the rows inside whatever JSON the model produced ─────────────────────────────────────

describe('findDistractorItems', () => {
  const row = { sentence: 'a', error_span: 'b', correction: 'c', error_type: 'article' }

  it('finds a top-level array', () => {
    const found = findDistractorItems([row])
    expect(found.items).toHaveLength(1)
    expect(found.path).toBe('')
  })

  it('finds the array under an obvious key', () => {
    expect(findDistractorItems({ distractors: [row] }).path).toBe('distractors')
    expect(findDistractorItems({ result: { data: { items: [row] } } }).path).toBe('result.data.items')
  })

  it('prefers the shallower array when there are two', () => {
    const found = findDistractorItems({ items: [row], deep: { items: [row, row] } })
    expect(found.path).toBe('items')
    expect(found.items).toHaveLength(1)
  })

  it('keeps error_type when present and leaves it out when not', () => {
    expect(findDistractorItems([row]).items[0].error_type).toBe('article')
    expect(findDistractorItems([{ sentence: 'a', error_span: 'b', correction: 'c' }]).items[0].error_type).toBeUndefined()
  })

  it('says which key is missing rather than just "not found"', () => {
    const found = findDistractorItems({ items: [{ sentence: 'a', span: 'b', correction: 'c' }] })
    expect(found.items).toHaveLength(0)
    expect(found.missing).toEqual(['error_span'])
    expect(itemsHint(found)).toContain('error_span')
    expect(itemsHint(found)).toContain('span')
  })

  it('reports plainly when there is no array of objects at all', () => {
    const found = findDistractorItems({ text: 'no json here' })
    expect(itemsHint(found)).toContain('нет массива объектов')
  })
})

// ── The screen ───────────────────────────────────────────────────────────────────────────────────

describe('PlaygroundView', () => {
  it('says out loud that nothing is written to the database', async () => {
    const { w } = await mountView()
    expect(w.text()).toContain('В базу не пишется ничего')
  })

  it('disables a provider with no key and names the env var', async () => {
    const { w } = await mountView()
    const options = w.find('select').findAll('option')
    const anthropic = options.find((o) => o.text().startsWith('Anthropic'))!

    expect((anthropic.element as HTMLOptionElement).disabled).toBe(true)
    expect(anthropic.text()).toContain('ANTHROPIC_API_KEY')
    // The usable provider is the one selected on arrival.
    expect((w.find('select').element as HTMLSelectElement).value).toBe('openai')
  })

  it('keeps «Отправить» disabled until there is a prompt', async () => {
    const { w } = await mountView()
    expect((button(w, 'Отправить').element as HTMLButtonElement).disabled).toBe(true)

    await w.find('textarea').setValue('верни json')
    expect((button(w, 'Отправить').element as HTMLButtonElement).disabled).toBe(false)
  })

  it('remembers the prompt in localStorage across a remount', async () => {
    const first = await mountView()
    await first.w.find('textarea').setValue('мой длинный промпт')
    expect(localStorage.getItem('wt_admin.playground.prompt')).toBe('мой длинный промпт')

    const second = await mountView()
    expect((second.w.find('textarea').element as HTMLTextAreaElement).value).toBe('мой длинный промпт')
  })

  it('renders the answer as a tree with usage, cost and latency', async () => {
    const { w } = await mountView()
    await w.find('textarea').setValue('верни json')
    await button(w, 'Отправить').trigger('click')
    await flushPromises()

    expect(w.text()).toContain('Ответ')
    expect(w.findComponent({ name: 'JsonTree' }).exists()).toBe(true)
    expect(w.text()).toContain('токены')
    expect(w.text()).toContain('стоимость')
    expect(w.text()).toContain('задержка')
  })

  it('only enables «Валидировать» once rows AND a reference are present', async () => {
    const { w } = await mountView()
    await w.find('textarea').setValue('верни json')
    await button(w, 'Отправить').trigger('click')
    await flushPromises()

    // Rows were found in the answer, but no reference has been chosen yet.
    expect(w.text()).toContain('Нашли 3 строк')
    expect((button(w, 'Валидировать').element as HTMLButtonElement).disabled).toBe(true)

    // Manual reference: two fields make it usable.
    await w.findAll('input[type="radio"]')[1].setValue()
    await flushPromises()
    const inputs = w.findAll('.manual input[type="text"]')
    await inputs[0].setValue('withdraw money')
    await inputs[1].setValue('I would like to withdraw money from my account.')
    expect((button(w, 'Валидировать').element as HTMLButtonElement).disabled).toBe(false)
  })

  it('shows KEEP/REJECT with a Russian reason and the survivor count', async () => {
    const { w } = await mountView()
    await w.find('textarea').setValue('верни json')
    await button(w, 'Отправить').trigger('click')
    await flushPromises()

    await w.findAll('input[type="radio"]')[1].setValue()
    await flushPromises()
    const inputs = w.findAll('.manual input[type="text"]')
    await inputs[0].setValue('withdraw money')
    await inputs[1].setValue('I would like to withdraw money from my account.')
    await button(w, 'Валидировать').trigger('click')
    await flushPromises()

    expect(w.text()).toContain('Выжило')
    const rows = w.findAll('.verdicts li')
    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('KEEP')
    expect(rows.some((r) => r.text().includes('REJECT'))).toBe(true)
    // The reason is a sentence, not the machine code repeated.
    expect(w.text()).toContain('span и correction совпадают')
    expect(w.text()).toContain('Ничего из этого не сохранено')
  })

  it('explains what is missing when the answer has no usable rows', async () => {
    const found = findDistractorItems({ items: [{ sentence: 'a' }] })
    expect(itemsHint(found)).toContain('Не хватает')
  })
})
