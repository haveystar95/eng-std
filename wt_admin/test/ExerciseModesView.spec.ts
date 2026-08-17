import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ExerciseModesView from '@/views/ExerciseModesView.vue'
import ModesTab from '@/views/user/ModesTab.vue'
import { mock } from '@/api/mock'

/** Tick the checkbox whose row mentions this wire name. */
async function toggle(w: ReturnType<typeof mount>, wire: string) {
  const row = w.findAll('label.row').find((l) => l.text().includes(wire))
  if (!row) throw new Error(`no toggle row for ${wire}`)
  await row.find('input').setValue(false)
}

describe('ExerciseModesView (global toggles)', () => {
  it('lists every available mode and marks the enabled ones', async () => {
    const w = mount(ExerciseModesView)
    await flushPromises()

    expect(w.text()).toContain('Общий набор')
    // `available` is what the build can deal, so every mode has a row — on or off. Counted from
    // the API rather than hard-coded, or this test needs editing every time a trainer ships.
    expect(w.findAll('label.row')).toHaveLength((await mock.getExerciseModes()).available.length)
    expect(w.text()).toContain('scramble')
  })

  it('names every row, even a trainer this build has never heard of', async () => {
    // `available` comes from the SERVER's enum, so a mode released after this panel was deployed
    // arrives as an unknown value. It rendered blank once (dictation) — a checkbox with no name is
    // a toggle you cannot identify. Untranslated is fine; nameless is not.
    const w = mount(ExerciseModesView)
    await flushPromises()

    for (const row of w.findAll('label.row')) {
      expect(row.find('.name').text()).not.toBe('')
    }
    expect(w.text()).toContain('диктант')
  })

  it('asks before a global flip, naming what turns off', async () => {
    const w = mount(ExerciseModesView)
    await flushPromises()

    await toggle(w, 'scramble')
    await w.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    // A global change touches everyone, so the dialog states the diff rather than "are you sure".
    const dialog = document.body.textContent ?? ''
    expect(dialog).toContain('Изменить общий набор?')
    expect(dialog).toContain('Выключаем')
    expect(dialog).toContain('предложение') // the scramble label
  })

  it('does not save until the change is confirmed', async () => {
    const before = (await mock.getExerciseModes()).global

    const w = mount(ExerciseModesView)
    await flushPromises()
    await toggle(w, 'listening')
    await w.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    await flushPromises()

    expect((await mock.getExerciseModes()).global).toEqual(before)
  })
})

describe('ModesTab (per-user override)', () => {
  /** A real id from the mock — the API 404s an unknown user, exactly like the server. */
  async function someUserId(): Promise<string> {
    return (await mock.listUsers({})).data[0].id
  }

  it('shows an inheriting user as following the common set', async () => {
    const w = mount(ModesTab, { props: { userId: await someUserId() } })
    await flushPromises()

    expect(w.text()).toContain('Общий набор')
    expect(w.text()).toContain('Как у всех')
    // No toggle list until the user actually takes a custom set.
    expect(w.findAll('label.row')).toHaveLength(0)
  })

  it('reveals the toggles once "своя настройка" is picked', async () => {
    const w = mount(ModesTab, { props: { userId: await someUserId() } })
    await flushPromises()

    await w.findAll('input[type="radio"]')[1].setValue(true)
    await flushPromises()

    expect(w.findAll('label.row').length).toBeGreaterThan(0)
  })
})
