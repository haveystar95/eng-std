<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAsync } from '@/composables/useAsync'
import { money } from '@/utils/format'
import { DIALOG_STATUS_LABEL } from '@/utils/labels'
import type { DialogRow } from '@/api/types'
import PaperCard from '@/components/PaperCard.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import Pagination from '@/components/Pagination.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{ userId: string }>()
const router = useRouter()

const { rows, meta, loading, error, load, goTo } = usePaginated<DialogRow>((page) =>
  api.listDialogs({ userId: props.userId, page }),
)
onMounted(load)

const selectedId = ref<string | null>(null)
const transcript = useAsync(() => api.getDialog(selectedId.value as string))
function openDialog(d: DialogRow) {
  selectedId.value = d.id
  transcript.run()
}
function shortId(id: string): string {
  return id.slice(-6)
}

const columns: Column[] = [
  { key: 'collection', label: 'Коллекция' },
  { key: 'status', label: 'Статус' },
  { key: 'tokens', label: 'Токены', align: 'right', tnum: true },
  { key: 'cost', label: 'Стоимость', align: 'right', tnum: true },
  { key: 'created', label: 'Когда', align: 'right' },
]
</script>

<template>
  <div class="dialogs">
    <PaperCard :pad="false" class="wrap">
      <StateBlock v-if="loading && rows.length === 0" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
      <StateBlock
        v-else-if="rows.length === 0"
        kind="empty"
        title="Нет диалогов"
        message="Пользователь ещё не запускал разговорную практику."
      />
      <template v-else>
        <DataTable :columns="columns" :rows="rows" :row-key="(d) => d.id" clickable @row-click="openDialog">
          <template #cell-collection="{ row }">
            <a class="clink" @click.stop="router.push({ name: 'collection', params: { id: row.collectionId } })">
              коллекция ···{{ shortId(row.collectionId) }}
            </a>
          </template>
          <template #cell-status="{ row }">
            <Badge :tone="row.status === 'finished' ? 'known' : 'neutral'">{{ DIALOG_STATUS_LABEL[row.status] }}</Badge>
          </template>
          <template #cell-tokens="{ row }">{{ (row.tokensIn ?? 0) + (row.tokensOut ?? 0) }}</template>
          <template #cell-cost="{ row }">{{ money(row.costUsd) }}</template>
          <template #cell-created="{ row }"><RelativeDate :value="row.createdAt" /></template>
        </DataTable>
        <div class="pad"><Pagination :meta="meta" @change="goTo" /></div>
      </template>
    </PaperCard>

    <PaperCard v-if="selectedId" class="transcript">
      <StateBlock v-if="transcript.loading.value" kind="loading" />
      <StateBlock
        v-else-if="transcript.error.value"
        kind="error"
        :message="transcript.error.value"
        retryable
        @retry="transcript.run"
      />
      <template v-else-if="transcript.data.value">
        <div class="t-head">
          <div class="section-label">Транскрипт</div>
          <span class="tnum">{{ money(transcript.data.value.costUsd) }}</span>
        </div>
        <p v-if="transcript.data.value.summary" class="summary muted">{{ transcript.data.value.summary }}</p>
        <div class="lines">
          <div v-for="(line, i) in transcript.data.value.transcript" :key="i" class="line" :class="line.role">
            <div class="bubble">{{ line.text }}</div>
          </div>
        </div>
      </template>
    </PaperCard>
  </div>
</template>

<style scoped>
.dialogs {
  display: flex;
  flex-direction: column;
  gap: var(--s16);
}
.wrap {
  overflow: hidden;
}
.pad {
  padding: 0 var(--s16) var(--s16);
}
.clink {
  color: var(--ink);
  border-bottom: 1px solid var(--track);
  cursor: pointer;
}
.clink:hover {
  border-color: var(--ink);
}
.t-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--s16);
  margin-bottom: var(--s8);
}
.summary {
  margin: 0 0 var(--s16);
  font-size: 13.5px;
  font-style: italic;
}
.lines {
  display: flex;
  flex-direction: column;
  gap: var(--s8);
}
.line {
  display: flex;
}
.line.user {
  justify-content: flex-end;
}
.bubble {
  max-width: 78%;
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 14px;
  line-height: 1.4;
}
.line.assistant .bubble {
  background: var(--faint-ink);
  color: var(--ink);
  border-top-left-radius: 4px;
}
.line.user .bubble {
  background: var(--ink);
  color: var(--paper);
  border-top-right-radius: 4px;
}
</style>
