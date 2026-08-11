<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePaginated } from '@/composables/usePaginated'
import { money } from '@/utils/format'
import { GENERATION_STATUS_LABEL, generationTone } from '@/utils/labels'
import type { Generation, Paginated } from '@/api/types'
import PaperCard from './PaperCard.vue'
import DataTable, { type Column } from './DataTable.vue'
import Badge from './Badge.vue'
import RelativeDate from './RelativeDate.vue'
import Pagination from './Pagination.vue'
import StateBlock from './StateBlock.vue'

// Reusable AI-generation viewer. Used by the global Generations page and a per-user
// tab; the parent supplies the fetcher (reading its filters) and calls reset().
const props = defineProps<{
  fetcher: (page: number) => Promise<Paginated<Generation>>
  showUser?: boolean
}>()

const router = useRouter()
const { rows, meta, loading, error, load, goTo, reset } = usePaginated<Generation>(props.fetcher)
onMounted(load)
defineExpose({ reset })

function shortId(id: string): string {
  return id.slice(-6)
}

const columns: Column[] = [
  { key: 'created', label: 'Когда', align: 'right' },
  { key: 'prompt', label: 'Запрос', width: '46%' },
  { key: 'status', label: 'Статус' },
  { key: 'model', label: 'Модель' },
  { key: 'tokens', label: 'Токены', align: 'right', tnum: true },
  { key: 'cost', label: 'Стоимость', align: 'right', tnum: true },
  ...(props.showUser ? [{ key: 'user', label: 'Юзер' } as Column] : []),
  { key: 'result', label: 'Результат' },
]
</script>

<template>
  <PaperCard :pad="false" class="wrap">
    <StateBlock v-if="loading && rows.length === 0" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
    <StateBlock
      v-else-if="rows.length === 0"
      kind="empty"
      title="Генераций нет"
      message="По текущим фильтрам запросов не найдено."
    />
    <template v-else>
      <DataTable :columns="columns" :rows="rows" :row-key="(g) => g.id">
        <template #cell-created="{ row }"><RelativeDate :value="row.createdAt" /></template>
        <template #cell-prompt="{ row }">
          <span class="prompt">{{ row.prompt }}</span>
        </template>
        <template #cell-status="{ row }">
          <Badge :tone="generationTone(row.status)">{{ GENERATION_STATUS_LABEL[row.status] }}</Badge>
        </template>
        <template #cell-model="{ row }"><span class="faint">{{ row.model ?? '—' }}</span></template>
        <template #cell-tokens="{ row }">{{ (row.tokensIn ?? 0) + (row.tokensOut ?? 0) }}</template>
        <template #cell-cost="{ row }">{{ money(row.costUsd) }}</template>
        <template #cell-user="{ row }">
          <a class="clink" @click="router.push({ name: 'user', params: { id: row.userId } })">
            ···{{ shortId(row.userId) }}
          </a>
        </template>
        <template #cell-result="{ row }">
          <a
            v-if="row.collectionId"
            class="clink"
            @click="router.push({ name: 'collection', params: { id: row.collectionId } })"
          >коллекция</a>
          <span v-else-if="row.error" class="err" :title="row.error">ошибка</span>
          <span v-else class="faint">—</span>
        </template>
      </DataTable>
      <div class="pad"><Pagination :meta="meta" @change="goTo" /></div>
    </template>
  </PaperCard>
</template>

<style scoped>
.wrap {
  overflow: hidden;
}
.pad {
  padding: 0 var(--s16) var(--s16);
}
.prompt {
  display: block;
  min-width: 320px;
  max-width: 620px;
  white-space: normal;
  word-break: break-word;
  line-height: 1.35;
}
.clink {
  color: var(--ink);
  border-bottom: 1px solid var(--track);
  cursor: pointer;
  font-size: 12.5px;
}
.clink:hover {
  border-color: var(--ink);
}
.err {
  color: var(--destructive);
  cursor: help;
}
</style>
