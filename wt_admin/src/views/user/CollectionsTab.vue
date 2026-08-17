<script setup lang="ts">
import { useRouter } from 'vue-router'
import { count } from '@/utils/format'
import { COLLECTION_TYPE_LABEL } from '@/utils/labels'
import type { CollectionType, UserCollection } from '@/api/types'
import PaperCard from '@/components/PaperCard.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StateBlock from '@/components/StateBlock.vue'

// Collections come embedded in the user detail (contract: UserDetail.collections),
// so this tab needs no separate fetch.
const props = defineProps<{ collections: UserCollection[] }>()
const router = useRouter()

const columns: Column[] = [
  { key: 'title', label: 'Коллекция' },
  { key: 'type', label: 'Тип' },
  { key: 'items', label: 'Терминов', align: 'right', tnum: true },
  { key: 'added', label: 'Добавлена', align: 'right' },
]
function typeLabel(t: string): string {
  return COLLECTION_TYPE_LABEL[t as CollectionType] ?? t
}
</script>

<template>
  <PaperCard :pad="false" class="wrap">
    <StateBlock
      v-if="props.collections.length === 0"
      kind="empty"
      title="Нет коллекций"
      message="Пользователь не добавил ни одной коллекции."
    />
    <DataTable
      v-else
      :columns="columns"
      :rows="props.collections"
      :row-key="(c) => c.id"
      clickable
      @row-click="(c) => router.push({ name: 'collection', params: { id: c.id } })"
    >
      <template #cell-title="{ row }"><span class="serif name">{{ row.title }}</span></template>
      <template #cell-type="{ row }"><Badge>{{ typeLabel(row.type) }}</Badge></template>
      <template #cell-items="{ row }">{{ count(row.itemsCount) }}</template>
      <template #cell-added="{ row }"><RelativeDate :value="row.addedAt" /></template>
    </DataTable>
  </PaperCard>
</template>

<style scoped>
.wrap {
  overflow: hidden;
}
.name {
  font-size: 16px;
}
</style>
