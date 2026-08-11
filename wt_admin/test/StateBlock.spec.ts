import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StateBlock from '@/components/StateBlock.vue'

describe('StateBlock', () => {
  it('renders an empty state with title and message', () => {
    const w = mount(StateBlock, { props: { kind: 'empty', title: 'Пусто', message: 'Ничего нет' } })
    expect(w.text()).toContain('Пусто')
    expect(w.text()).toContain('Ничего нет')
  })

  it('shows a retry button on error and emits retry', async () => {
    const w = mount(StateBlock, { props: { kind: 'error', message: 'Сбой', retryable: true } })
    const btn = w.find('button')
    expect(btn.exists()).toBe(true)
    await btn.trigger('click')
    expect(w.emitted('retry')).toHaveLength(1)
  })

  it('has no retry button when not retryable', () => {
    const w = mount(StateBlock, { props: { kind: 'error', message: 'Сбой' } })
    expect(w.find('button').exists()).toBe(false)
  })
})
