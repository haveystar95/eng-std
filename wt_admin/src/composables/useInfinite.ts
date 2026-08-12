import { ref, shallowRef } from 'vue'
import type { CursorQuery, Paginated } from '@/api/types'

/**
 * Cursor-driven list state for the infinite-scrolling tables.
 *
 * The BE walks by keyset (`limit` + `cursor`, ordered id DESC), so appending a page can never
 * duplicate or skip a row the way OFFSET does when new rows land mid-scroll. `meta.nextCursor`
 * being null is the end of the list — a running count compared against a total would be wrong the
 * moment anything is inserted.
 *
 * `loadMore` is guarded against re-entry: the scroll sentinel fires more than once while the
 * fetch is in flight, and without the guard the same page is requested several times.
 */
export function useInfinite<T>(
  fetcher: (q: CursorQuery) => Promise<Paginated<T>>,
  pageSize = 50,
) {
  const rows = shallowRef<T[]>([])
  const total = ref(0)
  const cursor = ref<string | null>(null)
  const done = ref(false)
  const loading = ref(false)
  const loadingMore = ref(false)
  const error = ref<string | null>(null)

  async function fetchPage(append: boolean) {
    if (loading.value || loadingMore.value) return
    if (append) loadingMore.value = true
    else loading.value = true
    error.value = null

    try {
      const res = await fetcher({
        limit: pageSize,
        cursor: append ? (cursor.value ?? undefined) : undefined,
      })
      rows.value = append ? [...rows.value, ...res.data] : res.data
      total.value = res.meta.total
      cursor.value = res.meta.nextCursor
      done.value = res.meta.nextCursor === null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
      if (!append) rows.value = []
      // A failed page must not be treated as the end — the sentinel can retry.
      done.value = false
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  /** First page — also the "filters changed" and "refresh" path. */
  function reload() {
    cursor.value = null
    done.value = false
    return fetchPage(false)
  }

  function loadMore() {
    if (done.value || cursor.value === null) return Promise.resolve()
    return fetchPage(true)
  }

  return { rows, total, loading, loadingMore, error, done, reload, loadMore }
}
