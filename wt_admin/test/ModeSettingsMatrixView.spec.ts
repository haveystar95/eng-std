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

/** The row whose wire name (`.wire`) matches, from a mounted table. Re-locate after any edit
 *  that can move a row between sections — each section is a separate `<table>`, so the row's DOM
 *  node is a fresh one once it relocates, not a re-keyed match of the old one. */
function rowFor(w: ReturnType<typeof mount>, wire: string) {
  const row = w.findAll('tbody tr').find((r) => r.find('.wire').text() === wire)
  if (!row) throw new Error(`no row for ${wire}`)
  return row
}

/** The heading of the `.phase-section` a row currently renders inside. */
function sectionTitleFor(w: ReturnType<typeof mount>, wire: string): string {
  const el = rowFor(w, wire).element.closest('.phase-section')
  if (!el) throw new Error(`row for ${wire} is not inside a .phase-section`)
  return el.querySelector('h3')?.textContent?.trim() ?? ''
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

describe('ModeSettingsMatrixView (sectioning by effective opening phase)', () => {
  it('groups rows under their effective-phase section heading', async () => {
    const { w } = await mountView()

    // Shipped defaults: intro floors at `new`, multiple_choice at `learning`, everything else
    // (cloze here) at `graduated` — see ModePassport on the backend, mirrored in the mock.
    expect(sectionTitleFor(w, 'intro')).toBe('Новые слова')
    expect(sectionTitleFor(w, 'multiple_choice')).toBe('Узнавание')
    expect(sectionTitleFor(w, 'cloze')).toBe('Выпущенные')
  })

  it('disables "Открывается с" options below a mode\'s passport floor, offers the rest', async () => {
    const { w } = await mountView()

    // speaking's floor is `graduated` — nothing below it is a real choice.
    const speaking = rowFor(w, 'speaking').find('select')
    const speakingOptions = Object.fromEntries(
      speaking.findAll('option').map((o) => [(o.element as HTMLOptionElement).value, (o.element as HTMLOptionElement).disabled]),
    )
    expect(speakingOptions).toEqual({ new: true, learning: true, graduated: false })

    // multiple_choice's floor is `learning` — `new` is unreachable, `learning`/`graduated` are not.
    const mc = rowFor(w, 'multiple_choice').find('select')
    const mcOptions = Object.fromEntries(
      mc.findAll('option').map((o) => [(o.element as HTMLOptionElement).value, (o.element as HTMLOptionElement).disabled]),
    )
    expect(mcOptions).toEqual({ new: true, learning: false, graduated: false })
  })

  it('shows a legacy row below its floor under its effective phase, flagged "фактически"', async () => {
    // Simulates data written before the passport floor existed (or by a rolled-back build) — the
    // mock, like the real backend before this наряд, does not itself refuse the write; only the
    // admin screen has to make sense of what is already on disk.
    await mock.saveModeSettingsRow({
      mode: 'listening',
      enabled: true,
      position: 5,
      minAcquisition: 'new',
      minLearningStep: null,
      minSuccessfulReviews: null,
      optionsPolicy: 'standard',
    })

    const { w } = await mountView()

    // listening's floor is `graduated`, so a `new` reading opens it nowhere — it renders under
    // the section it ACTUALLY opens in, not the one its stored value names.
    expect(sectionTitleFor(w, 'listening')).toBe('Выпущенные')
    expect(rowFor(w, 'listening').text()).toContain('фактически: выпущено')
  })

  it('moving a row to a new phase and saving relocates it there', async () => {
    const { w } = await mountView()

    // multiple_choice ships at `learning` (its own floor); graduated is above its floor, so
    // moving it there is a legitimate change, not one the floor guard would refuse.
    expect(sectionTitleFor(w, 'multiple_choice')).toBe('Узнавание')
    const before = rowFor(w, 'multiple_choice')
    await before.find('select').setValue('graduated')
    await flushPromises()

    // Live move: the section reflects the edited draft before Save is even clicked.
    expect(sectionTitleFor(w, 'multiple_choice')).toBe('Выпущенные')

    // The row is now inside a different <table> — re-locate before interacting with it further.
    const moved = rowFor(w, 'multiple_choice')
    await moved.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    expect(sectionTitleFor(w, 'multiple_choice')).toBe('Выпущенные')
    const saved = (await mock.getModeSettingsMatrix()).rows.find((r) => r.mode === 'multiple_choice')!
    expect(saved.minAcquisition).toBe('graduated')
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
