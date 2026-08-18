import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import ModeSettingsMatrixView from '@/views/ModeSettingsMatrixView.vue'
import { mock } from '@/api/mock'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/mode-settings', name: 'mode-settings', component: ModeSettingsMatrixView }],
  })
}

async function mountView() {
  const router = makeRouter()
  router.push('/mode-settings')
  await router.isReady()
  const w = mount(ModeSettingsMatrixView, { global: { plugins: [router] } })
  await flushPromises()
  return { w, router }
}

/** The row whose wire name (`.wire`) matches, from a mounted table. */
function rowFor(w: ReturnType<typeof mount>, wire: string) {
  const row = w.findAll('tbody tr').find((r) => r.find('.wire').text() === wire)
  if (!row) throw new Error(`no row for ${wire}`)
  return row
}

async function someUserId(): Promise<string> {
  return (await mock.listUsers({})).data[0].id
}

describe('ModeSettingsMatrixView (global scope)', () => {
  it('lists every mode this build knows, all tagged as the global source', async () => {
    const { w } = await mountView()

    expect(w.text()).toContain('Матрица режимов')
    const matrix = await mock.getModeSettingsMatrix()
    expect(w.findAll('tbody tr')).toHaveLength(matrix.rows.length)
    expect(w.text()).toContain('typing')
    expect(w.findAll('.badge').every((b) => b.text() === 'общее')).toBe(true)
  })

  it('only enables Сохранить once a row is actually edited', async () => {
    const { w } = await mountView()
    const row = rowFor(w, 'typing')
    const saveBtn = row.findAll('button').find((b) => b.text() === 'Сохранить')!
    expect((saveBtn.element as HTMLButtonElement).disabled).toBe(true)

    await row.find('input[type="number"]').setValue(9)
    expect((saveBtn.element as HTMLButtonElement).disabled).toBe(false)
  })

  it('saves one row without touching the others', async () => {
    const { w } = await mountView()
    const before = await mock.getModeSettingsMatrix()
    const otherBefore = before.rows.find((r) => r.mode === 'dictation')!

    const row = rowFor(w, 'typing')
    await row.find('input[type="number"]').setValue(9)
    await row.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    const after = await mock.getModeSettingsMatrix()
    expect(after.rows.find((r) => r.mode === 'typing')!.position).toBe(9)
    expect(after.rows.find((r) => r.mode === 'dictation')).toEqual(otherBefore)
  })

  it('disables the reviews field off graduated, and the step field off learning', async () => {
    const { w } = await mountView()
    const typingRow = rowFor(w, 'typing') // shipped: graduated
    const introRow = rowFor(w, 'intro') // shipped: new

    const [, stepInput, reviewsInput] = typingRow.findAll('input[type="number"]')
    expect((stepInput.element as HTMLInputElement).disabled).toBe(true) // not `learning`
    expect((reviewsInput.element as HTMLInputElement).disabled).toBe(false) // is `graduated`

    const [, introStep, introReviews] = introRow.findAll('input[type="number"]')
    expect((introStep.element as HTMLInputElement).disabled).toBe(true)
    expect((introReviews.element as HTMLInputElement).disabled).toBe(true)
  })
})

describe('ModeSettingsMatrixView (per-user override)', () => {
  it('puts the chosen user in the URL query string', async () => {
    const { w, router } = await mountView()
    const id = await someUserId()

    await w.find('select').setValue(id)
    await flushPromises()

    expect(router.currentRoute.value.query.user).toBe(id)
  })

  // Flips a checkbox rather than setting a magic position number: the mock's module-level state
  // persists across every `it` in this file (no reset between tests), so a fixed numeric literal
  // can collide with whatever an earlier test already wrote and become a silent no-op. A boolean
  // flip is safe regardless of run order — it always differs from whichever state it starts in.
  it('marks a saved user row as override, offers Сбросить, and leaves the global row alone', async () => {
    const { w } = await mountView()
    const id = await someUserId()
    await w.find('select').setValue(id)
    await flushPromises()

    const row = rowFor(w, 'dictation')
    const wasEnabled = (row.find('input[type="checkbox"]').element as HTMLInputElement).checked
    await row.find('input[type="checkbox"]').setValue(!wasEnabled)
    await row.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    const saved = (await mock.getUserModeSettingsMatrix(id)).rows.find((r) => r.mode === 'dictation')!
    expect(saved.source).toBe('override')
    expect(saved.enabled).toBe(!wasEnabled)
    expect((await mock.getModeSettingsMatrix()).rows.find((r) => r.mode === 'dictation')!.enabled).toBe(wasEnabled)

    expect(w.text()).toContain('своё')
    expect(w.findAll('button').some((b) => b.text() === 'Сбросить')).toBe(true)
  })

  it('resets an override back onto the global row', async () => {
    const { w } = await mountView()
    const id = await someUserId()
    await w.find('select').setValue(id)
    await flushPromises()

    const row = rowFor(w, 'dictation')
    const wasEnabled = (row.find('input[type="checkbox"]').element as HTMLInputElement).checked
    await row.find('input[type="checkbox"]').setValue(!wasEnabled)
    await row.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    await w.findAll('button').find((b) => b.text() === 'Сбросить')!.trigger('click')
    await flushPromises()

    const after = (await mock.getUserModeSettingsMatrix(id)).rows.find((r) => r.mode === 'dictation')!
    expect(after.source).toBe('global')
    expect(w.findAll('button').some((b) => b.text() === 'Сбросить')).toBe(false)
  })
})
