<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { plural } from '@/utils/format'
import { MODE_LABEL, STATE_LABEL, stateTone } from '@/utils/labels'
import type { PlanEntry } from '@/api/types'
import PaperCard from '@/components/PaperCard.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{ userId: string; timezone?: string | null }>()

function isoDate(offsetDays: number): string {
  const d = new Date()
  d.setDate(d.getDate() + offsetDays)
  return d.toISOString().slice(0, 10)
}
const today = isoDate(0)
const tomorrow = isoDate(1)
const selected = ref(today)

const { data, loading, error, run } = useAsync(() => api.getUserPlan(props.userId, selected.value))
watch(selected, run)
onMounted(run)

const quick = computed(() => (selected.value === today ? 'today' : selected.value === tomorrow ? 'tomorrow' : 'custom'))
const entries = computed<PlanEntry[]>(() => data.value?.entries ?? [])

const columns: Column[] = [
  { key: 'text', label: 'Слово' },
  { key: 'state', label: 'Состояние' },
  { key: 'reps', label: 'Повт.', align: 'right', tnum: true },
  { key: 'interval', label: 'Интервал', align: 'right', tnum: true },
  { key: 'due', label: 'Срок', align: 'right' },
  { key: 'mode', label: 'Тренажёр' },
]
</script>

<template>
  <div>
    <div class="controls">
      <div class="switch">
        <button :class="{ active: quick === 'today' }" @click="selected = today">Сегодня</button>
        <button :class="{ active: quick === 'tomorrow' }" @click="selected = tomorrow">Завтра</button>
      </div>
      <label class="date">
        <span class="section-label">Дата</span>
        <input v-model="selected" type="date" />
      </label>
      <span v-if="data?.timezone" class="tz faint">пояс: {{ data.timezone }}</span>
    </div>

    <PaperCard :pad="false" class="wrap">
      <StateBlock v-if="loading" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
      <StateBlock
        v-else-if="entries.length === 0"
        kind="empty"
        title="На эту дату карточек нет"
        message="У пользователя нет слов, попадающих в план на выбранный день."
      />
      <template v-else-if="data">
        <div class="summary">
          <span>{{ data.dueCount }} {{ plural(data.dueCount, 'к повтору', 'к повтору', 'к повтору') }}</span>
          <span class="dot">·</span>
          <span>{{ data.newIntroduced }} новых (лимит {{ data.newTermsPerDay }}/день)</span>
        </div>
        <DataTable :columns="columns" :rows="entries" :row-key="(e) => e.termId">
          <template #cell-text="{ row }">
            <span class="serif term">{{ row.text }}</span>
            <Badge v-if="row.isNew" tone="unsure" class="newb">новое</Badge>
          </template>
          <template #cell-state="{ row }">
            <Badge :tone="stateTone(row.state)">{{ STATE_LABEL[row.state] }}</Badge>
          </template>
          <template #cell-reps="{ row }">{{ row.reps }}</template>
          <template #cell-interval="{ row }">
            {{ row.intervalDays }} {{ plural(row.intervalDays, 'день', 'дня', 'дней') }}
          </template>
          <template #cell-due="{ row }"><RelativeDate :value="row.dueAt" /></template>
          <template #cell-mode="{ row }"><Badge>{{ MODE_LABEL[row.exerciseMode] }}</Badge></template>
        </DataTable>
      </template>
    </PaperCard>
  </div>
</template>

<style scoped>
.controls {
  display: flex;
  align-items: flex-end;
  gap: var(--s16);
  margin-bottom: var(--s16);
  flex-wrap: wrap;
}
.switch {
  display: inline-flex;
  gap: 2px;
  padding: 4px;
  background: var(--faint-ink);
  border-radius: var(--r-pill);
}
.switch button {
  border: none;
  background: transparent;
  border-radius: var(--r-pill);
  padding: 8px 16px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--secondary);
}
.switch button.active {
  background: var(--surface-raised);
  color: var(--ink);
  font-weight: 700;
  box-shadow: var(--shadow-card);
}
.date {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.date input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
}
.tz {
  font-size: 12px;
  padding-bottom: 10px;
}
.summary {
  display: flex;
  gap: var(--s8);
  padding: var(--s12) var(--s16) 0;
  font-size: 13px;
  color: var(--secondary);
}
.dot {
  color: var(--tertiary);
}
.term {
  font-size: 16px;
}
.newb {
  margin-left: var(--s8);
}
.wrap {
  overflow: hidden;
  padding-bottom: var(--s8);
}
</style>
