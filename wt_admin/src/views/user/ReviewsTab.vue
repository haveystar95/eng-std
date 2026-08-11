<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { GRADE_LABEL, MODE_LABEL, gradeTone } from '@/utils/labels'
import type { Review } from '@/api/types'
import PaperCard from '@/components/PaperCard.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import Pagination from '@/components/Pagination.vue'
import StateBlock from '@/components/StateBlock.vue'
import PaperButton from '@/components/PaperButton.vue'

const props = defineProps<{ userId: string }>()

const from = ref('')
const to = ref('')
const { rows, meta, loading, error, load, goTo, reset } = usePaginated<Review>((page) =>
  api.getUserReviews(props.userId, { from: from.value || undefined, to: to.value || undefined, page }),
)
onMounted(load)
function applyFilter() {
  reset()
}
function clearFilter() {
  from.value = ''
  to.value = ''
  reset()
}

const columns: Column[] = [
  { key: 'term', label: 'Слово' },
  { key: 'mode', label: 'Режим' },
  { key: 'grade', label: 'Оценка' },
  { key: 'correct', label: 'Верно' },
  { key: 'flags', label: '' },
  { key: 'answered', label: 'Когда', align: 'right' },
]
</script>

<template>
  <div>
    <div class="filter">
      <label class="f"><span class="section-label">С</span><input v-model="from" type="date" /></label>
      <label class="f"><span class="section-label">По</span><input v-model="to" type="date" /></label>
      <PaperButton variant="quiet" small @click="applyFilter">Применить</PaperButton>
      <PaperButton v-if="from || to" variant="ghost" small @click="clearFilter">Сбросить</PaperButton>
    </div>

    <PaperCard :pad="false" class="wrap">
      <StateBlock v-if="loading && rows.length === 0" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
      <StateBlock
        v-else-if="rows.length === 0"
        kind="empty"
        title="Нет ревью"
        message="За выбранный период ответов не найдено."
      />
      <template v-else>
        <DataTable :columns="columns" :rows="rows" :row-key="(r) => r.id">
          <template #cell-term="{ row }"><span class="serif term">{{ row.termText ?? '—' }}</span></template>
          <template #cell-mode="{ row }">
            <Badge v-if="row.exerciseMode">{{ MODE_LABEL[row.exerciseMode] }}</Badge>
            <span v-else class="faint">—</span>
          </template>
          <template #cell-grade="{ row }">
            <Badge :tone="gradeTone(row.grade)">{{ GRADE_LABEL[row.grade] }}</Badge>
          </template>
          <template #cell-correct="{ row }">
            <span v-if="row.isCorrect === true" class="ok">✓</span>
            <span v-else-if="row.isCorrect === false" class="no">✕</span>
            <span v-else class="faint">—</span>
          </template>
          <template #cell-flags="{ row }">
            <Badge v-if="row.isPractice" tone="unsure">практика</Badge>
          </template>
          <template #cell-answered="{ row }"><RelativeDate :value="row.answeredAt" /></template>
        </DataTable>
        <div class="pad"><Pagination :meta="meta" @change="goTo" /></div>
      </template>
    </PaperCard>
  </div>
</template>

<style scoped>
.filter {
  display: flex;
  align-items: flex-end;
  gap: var(--s12);
  margin-bottom: var(--s16);
  flex-wrap: wrap;
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
}
.wrap {
  overflow: hidden;
}
.pad {
  padding: 0 var(--s16) var(--s16);
}
.term {
  font-size: 16px;
}
.ok {
  color: var(--verdict-known);
  font-weight: 700;
}
.no {
  color: var(--verdict-unknown);
  font-weight: 700;
}
</style>
