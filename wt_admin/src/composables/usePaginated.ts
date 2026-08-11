import { ref, shallowRef, watch } from 'vue'
import type { PageMeta, Paginated } from '@/api/types'

// Paginated list state: current page, rows, meta, loading/error. `fetcher` receives
// the page and returns the server envelope. Changing filters should call reset().
export function usePaginated<T>(fetcher: (page: number) => Promise<Paginated<T>>, perPage = 25) {
  const rows = shallowRef<T[]>([])
  const meta = ref<PageMeta>({ page: 1, perPage, total: 0, totalPages: 1 })
  const loading = ref(false)
  const error = ref<string | null>(null)
  const page = ref(1)

  async function load() {
    loading.value = true
    error.value = null
    try {
      const res = await fetcher(page.value)
      rows.value = res.data
      meta.value = res.meta
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
      rows.value = []
    } finally {
      loading.value = false
    }
  }

  watch(page, load)

  function goTo(p: number) {
    page.value = p
  }
  function reset() {
    if (page.value === 1) load()
    else page.value = 1 // triggers watcher → load
  }

  return { rows, meta, loading, error, page, load, goTo, reset }
}
