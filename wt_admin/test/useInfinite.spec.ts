import { describe, expect, it, vi } from 'vitest'
import { useInfinite } from '@/composables/useInfinite'
import type { CursorQuery, Paginated } from '@/api/types'

function page(ids: string[], nextCursor: string | null, total = 6): Paginated<{ id: string }> {
  return {
    data: ids.map((id) => ({ id })),
    meta: { page: 1, perPage: ids.length, total, totalPages: 1, nextCursor },
  }
}

describe('useInfinite', () => {
  it('appends pages by cursor and stops when the server says there is no next one', async () => {
    const fetcher = vi
      .fn<[CursorQuery], Promise<Paginated<{ id: string }>>>()
      .mockResolvedValueOnce(page(['c', 'b'], 'b'))
      .mockResolvedValueOnce(page(['a'], null))

    const list = useInfinite(fetcher, 2)
    await list.reload()

    expect(list.rows.value.map((r) => r.id)).toEqual(['c', 'b'])
    expect(list.done.value).toBe(false)
    // First page must NOT send a cursor — it is the start of the walk.
    expect(fetcher).toHaveBeenCalledWith({ limit: 2, cursor: undefined })

    await list.loadMore()

    expect(list.rows.value.map((r) => r.id)).toEqual(['c', 'b', 'a'])
    expect(list.done.value).toBe(true)
    expect(fetcher).toHaveBeenLastCalledWith({ limit: 2, cursor: 'b' })

    // Past the end, the sentinel keeps firing — it must not keep asking.
    await list.loadMore()
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('reload starts the walk over, dropping the accumulated rows', async () => {
    const fetcher = vi
      .fn<[CursorQuery], Promise<Paginated<{ id: string }>>>()
      .mockResolvedValueOnce(page(['c', 'b'], 'b'))
      .mockResolvedValueOnce(page(['z'], null, 1))

    const list = useInfinite(fetcher, 2)
    await list.reload()
    await list.reload()

    expect(list.rows.value.map((r) => r.id)).toEqual(['z'])
    expect(fetcher).toHaveBeenLastCalledWith({ limit: 2, cursor: undefined })
  })

  it('a failed page is not the end of the list', async () => {
    const fetcher = vi
      .fn<[CursorQuery], Promise<Paginated<{ id: string }>>>()
      .mockResolvedValueOnce(page(['c', 'b'], 'b'))
      .mockRejectedValueOnce(new Error('сеть отвалилась'))

    const list = useInfinite(fetcher, 2)
    await list.reload()
    await list.loadMore()

    expect(list.error.value).toBe('сеть отвалилась')
    // Rows already on screen stay; done must stay false so a retry is possible.
    expect(list.rows.value).toHaveLength(2)
    expect(list.done.value).toBe(false)
  })
})
