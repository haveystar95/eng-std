import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import InfiniteList from '@/components/InfiniteList.vue'

/**
 * The component measures the sentinel against the viewport, so the tests control both: where the
 * sentinel claims to be, and how tall the window is. rAF is made synchronous so a scroll event
 * settles within the test.
 */
let sentinelTop = 5000

beforeEach(() => {
  sentinelTop = 5000
  vi.stubGlobal('innerHeight', 800)
  vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
    cb(0)
    return 1
  })
  vi.stubGlobal('cancelAnimationFrame', () => {})
  vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(
    () => ({ top: sentinelTop }) as DOMRect,
  )
})
afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const base = {
  loading: true,
  loadingMore: false,
  error: null,
  done: false,
  count: 0,
  total: 120,
}

function mountList(props: Partial<typeof base> = {}) {
  return mount(InfiniteList, {
    props: { ...base, ...props },
    slots: { default: '<div>rows</div>' },
    attachTo: document.body,
  })
}

describe('InfiniteList', () => {
  it('does not ask for more while the sentinel is far below the fold', async () => {
    const w = mountList({ loading: false, count: 50 })
    await flushPromises()

    window.dispatchEvent(new Event('scroll'))
    expect(w.emitted('more')).toBeUndefined()
  })

  it('asks for more once the sentinel scrolls near the fold', async () => {
    const w = mountList({ loading: false, count: 50 })
    await flushPromises()

    sentinelTop = 900 // within the 400px lookahead of an 800px viewport
    window.dispatchEvent(new Event('scroll'))

    expect(w.emitted('more')).toHaveLength(1)
  })

  it('fills a viewport the first page did not reach, without waiting for a scroll', async () => {
    // The bug this guards: a tall screen shows all 50 rows at once, so no scroll event ever
    // happens and the list silently stops — looking exactly like "there is no more data".
    sentinelTop = 300
    const w = mountList({ loading: true, count: 0 })
    await flushPromises()
    expect(w.emitted('more')).toBeUndefined() // still loading: nothing to measure yet

    await w.setProps({ loading: false, count: 50 })
    await flushPromises()

    expect(w.emitted('more')).toHaveLength(1)
  })

  it('stops asking once the server says there is no next page', async () => {
    sentinelTop = 100
    const w = mountList({ loading: false, count: 120, done: true })
    await flushPromises()

    window.dispatchEvent(new Event('scroll'))
    expect(w.emitted('more')).toBeUndefined()
    expect(w.text()).toContain('Всё — 120')
  })

  it('does not stack requests while a page is already in flight', async () => {
    sentinelTop = 100
    const w = mountList({ loading: false, count: 50, loadingMore: true })
    await flushPromises()

    window.dispatchEvent(new Event('scroll'))
    window.dispatchEvent(new Event('scroll'))
    expect(w.emitted('more')).toBeUndefined()
  })

  it('offers a visible button while pages remain, and drops it at the end', async () => {
    const w = mountList({ loading: false, count: 50 })
    await flushPromises()

    const button = w.find('.more')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('50')
    expect(button.text()).toContain('120')

    await button.trigger('click')
    expect(w.emitted('more')).toHaveLength(1)

    await w.setProps({ done: true, count: 120 })
    expect(w.find('.more').exists()).toBe(false)
  })
})
