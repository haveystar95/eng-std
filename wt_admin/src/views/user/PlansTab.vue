<script setup lang="ts">
// «Планы» of a learner's card (ADM-1): code, title, status, event date, «день N из M», total $.
// A row opens the plan's page.
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import type { LearnerPlanRow } from '@/api/planTypes'
import { useAsync } from '@/composables/useAsync'
import DataTable from '@/components/DataTable.vue'
import type { Column } from '@/components/DataTable.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StateBlock from '@/components/StateBlock.vue'
import StatusChip from '@/components/StatusChip.vue'
import { usdOrNa } from '@/utils/planLabels'

const props = defineProps<{ userId: string }>()
const router = useRouter()
const { data: plans, loading, error, run } = useAsync(() => api.listLearnerPlans(props.userId))
onMounted(run)

const columns: Column[] = [
  { key: 'code', label: 'Код' },
  { key: 'title', label: 'Название' },
  { key: 'status', label: 'Статус' },
  { key: 'event_date', label: 'Событие', tnum: true },
  { key: 'progress', label: 'Прогресс', tnum: true },
  { key: 'created_at', label: 'Создан' },
  { key: 'cost_usd', label: 'Итого $', align: 'right', tnum: true },
]

function open(row: LearnerPlanRow) {
  router.push({ name: 'plan', params: { code: row.code } })
}
</script>

<template>
  <StateBlock v-if="loading" kind="loading" />
  <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
  <StateBlock v-else-if="plans && !plans.length" kind="empty" title="Планов нет" message="Ученик ещё не создавал план." />
  <DataTable v-else-if="plans" :columns="columns" :rows="plans" :row-key="(r: LearnerPlanRow) => r.id" clickable @row-click="open">
    <template #cell-code="{ row }"><code class="code">{{ row.code }}</code></template>
    <template #cell-title="{ row }">{{ row.title_native ?? '—' }}</template>
    <template #cell-status="{ row }"><StatusChip :status="row.status" /></template>
    <template #cell-event_date="{ row }">{{ row.event_date ?? '—' }}</template>
    <template #cell-progress="{ row }">день {{ row.progress.current_day ?? row.progress.days_total }} из {{ row.progress.days_total }}</template>
    <template #cell-created_at="{ row }"><RelativeDate :value="row.created_at" /></template>
    <template #cell-cost_usd="{ row }">{{ usdOrNa(row.cost_usd) }}</template>
  </DataTable>
</template>

<style scoped>
.code {
  font-weight: 700;
  letter-spacing: 0.04em;
}
</style>
