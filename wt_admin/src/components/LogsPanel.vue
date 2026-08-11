<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePaginated } from '@/composables/usePaginated'
import { statusTone } from '@/utils/labels'
import type { Paginated, RequestLog } from '@/api/types'
import PaperCard from './PaperCard.vue'
import DataTable, { type Column } from './DataTable.vue'
import Badge from './Badge.vue'
import RelativeDate from './RelativeDate.vue'
import Pagination from './Pagination.vue'
import StateBlock from './StateBlock.vue'

// Reusable log viewer over api_request_logs. The admin contract exposes only row
// metadata — no request/response bodies and no per-id detail endpoint — so rows are
// not expandable here. The parent supplies the fetcher (reading its own filters) and
// calls reset() when filters change.
const props = defineProps<{
  fetcher: (page: number) => Promise<Paginated<RequestLog>>
  showUser?: boolean
}>()

const router = useRouter()
const { rows, meta, loading, error, load, goTo, reset } = usePaginated<RequestLog>(props.fetcher)
onMounted(load)
defineExpose({ reset })

function shortId(id: string): string {
  return id.slice(-6)
}

const columns: Column[] = [
  { key: 'occurred', label: 'Когда', align: 'right' },
  { key: 'dir', label: '' },
  { key: 'method', label: 'Метод' },
  { key: 'path', label: 'Путь' },
  { key: 'status', label: 'Статус', align: 'right' },
  { key: 'duration', label: 'мс', align: 'right', tnum: true },
  ...(props.showUser ? [{ key: 'user', label: 'Пользователь' } as Column] : []),
]
</script>

<template>
  <PaperCard :pad="false" class="wrap">
    <StateBlock v-if="loading && rows.length === 0" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
    <StateBlock
      v-else-if="rows.length === 0"
      kind="empty"
      title="Логов нет"
      message="По текущим фильтрам записей не найдено."
    />
    <template v-else>
      <DataTable :columns="columns" :rows="rows" :row-key="(r) => r.id">
        <template #cell-occurred="{ row }"><RelativeDate :value="row.occurredAt" /></template>
        <template #cell-dir="{ row }">
          <span class="dir" :class="row.direction" :title="row.direction === 'inbound' ? 'входящий' : 'исходящий'">
            {{ row.direction === 'inbound' ? '↓' : '↑' }}
          </span>
        </template>
        <template #cell-method="{ row }"><span class="method tnum">{{ row.method }}</span></template>
        <template #cell-path="{ row }">
          <span class="path">{{ row.path }}</span>
          <span v-if="row.service" class="svc faint">· {{ row.service }}</span>
        </template>
        <template #cell-status="{ row }">
          <Badge :tone="statusTone(row.status)">{{ row.status ?? '—' }}</Badge>
        </template>
        <template #cell-duration="{ row }">{{ row.durationMs ?? '—' }}</template>
        <template #cell-user="{ row }">
          <a
            v-if="row.userId"
            class="clink"
            @click="router.push({ name: 'user', params: { id: row.userId } })"
          >···{{ shortId(row.userId) }}</a>
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
.method {
  font-weight: 700;
  font-size: 12px;
}
.path {
  font-family: var(--font-ui);
}
.svc {
  font-size: 12px;
  margin-left: 4px;
}
.dir {
  font-weight: 700;
}
.dir.inbound {
  color: var(--verdict-known);
}
.dir.outbound {
  color: var(--verdict-unsure);
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
</style>
