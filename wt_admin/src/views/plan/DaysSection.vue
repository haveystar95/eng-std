<script setup lang="ts">
// «Обзор» (ADM-1): the plan's days — kind, scene, date by the schedule, build and walk status, when
// it was walked and for how long, and what it cost. No actions; «JSON» on a row shows that row.
import { ref, toRef } from 'vue'
import type { PlanDayRow, PlanDays } from '@/api/planTypes'
import DataTable from '@/components/DataTable.vue'
import type { Column } from '@/components/DataTable.vue'
import JsonBlock from '@/components/JsonBlock.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StatusChip from '@/components/StatusChip.vue'
import { usdOrNa, DAY_TYPE_LABEL } from '@/utils/planLabels'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanDays>(toRef(props, 'code'), 'days', toRef(props, 'day'))

const columns: Column[] = [
  { key: 'number', label: '№', tnum: true, width: '40px' },
  { key: 'type', label: 'Вид' },
  { key: 'scene', label: 'Сцена' },
  { key: 'opens_on', label: 'По расписанию', tnum: true },
  { key: 'status', label: 'День' },
  { key: 'lesson_status', label: 'Сборка' },
  { key: 'walk', label: 'Прохождение' },
  { key: 'cost', label: 'Цена дня', align: 'right', tnum: true },
  { key: 'json', label: '', align: 'right' },
]

const open = ref<number | null>(null)
</script>

<template>
  <PlanBlock
    id="days"
    title="Обзор"
    subtitle="Дни плана: статус сборки и прохождения, цена"
    :raw="data"
    :filename="jsonName(code, 'days', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <DataTable :columns="columns" :rows="data.data" :row-key="(r: PlanDayRow) => String(r.number)">
        <template #cell-type="{ row }">{{ DAY_TYPE_LABEL[row.type] ?? row.type }}</template>
        <template #cell-scene="{ row }">
          <span v-if="row.scene_title_native">{{ row.scene_title_native }}</span>
          <span v-else class="faint">—</span>
        </template>
        <template #cell-opens_on="{ row }">{{ row.opens_on ?? '—' }}</template>
        <template #cell-status="{ row }">
          <StatusChip :status="row.status" />
        </template>
        <template #cell-lesson_status="{ row }">
          <StatusChip v-if="row.lesson_status" :status="row.lesson_status" />
          <span v-else class="faint">без урока</span>
        </template>
        <template #cell-walk="{ row }">
          <span v-if="row.closed_at" class="walk">
            <RelativeDate :value="row.closed_at" /> · {{ row.minutes_spent }} мин · {{ row.cards_done }}/{{ row.cards_total }} карт.
            <span v-if="row.passed_at" class="faint"> · итог записан</span>
            <span v-else class="warn"> · итога нет</span>
          </span>
          <span v-else-if="row.opened_at" class="walk">открыт <RelativeDate :value="row.opened_at" /> · {{ row.cards_done }}/{{ row.cards_total }}</span>
          <span v-else class="faint">не начат</span>
        </template>
        <template #cell-cost="{ row }">{{ usdOrNa(row.cost_usd.total) }}</template>
        <template #cell-json="{ row }">
          <button class="row-json" @click="open = open === row.number ? null : row.number">JSON</button>
        </template>
      </DataTable>
      <JsonBlock v-if="open !== null" :title="`день ${open}`" :value="data.data.find((r) => r.number === open)" open />
    </template>
  </PlanBlock>
</template>

<style scoped>
.walk {
  font-size: 13px;
}
.warn {
  color: var(--verdict-unsure);
}
.row-json {
  border: none;
  background: transparent;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.row-json:hover {
  color: var(--ink);
  text-decoration: underline;
}
</style>
