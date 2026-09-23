// One section of the plan page, loaded for the page's day filter and loaded again when it changes.
import { onMounted, watch, type Ref } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import type { PlanSection } from '@/api/planTypes'

export function usePlanSection<T>(code: Ref<string>, section: PlanSection, day: Ref<number | null>) {
  const state = useAsync(() => api.getPlanSection<T>(code.value, section, day.value))
  onMounted(state.run)
  watch([code, day], () => void state.run())
  return state
}

/** «План K7M2QX · день 2» → `plan-K7M2QX-day2-issues.json`. */
export function jsonName(code: string, section: string, day: number | null): string {
  return `plan-${code}${day === null ? '' : `-day${day}`}-${section}.json`
}
