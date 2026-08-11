import { ref, shallowRef } from 'vue'

// Minimal async-resource helper: tracks loading/error/data for one fetch and
// re-runs on demand. Keeps every view's loading/error/empty handling uniform.
export function useAsync<T>(fetcher: () => Promise<T>) {
  const data = shallowRef<T | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function run() {
    loading.value = true
    error.value = null
    try {
      data.value = await fetcher()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, run }
}
