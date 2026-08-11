<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { count } from '@/utils/format'
import { COLLECTION_SOURCE_LABEL, COLLECTION_TYPE_LABEL } from '@/utils/labels'
import type { CollectionRow, CollectionType } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import SearchInput from '@/components/SearchInput.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import Pagination from '@/components/Pagination.vue'
import StateBlock from '@/components/StateBlock.vue'

const router = useRouter()
const search = ref('')
const typeFilter = ref<'' | CollectionType>('')

const TYPES: { key: '' | CollectionType; label: string }[] = [
  { key: '', label: 'Все' },
  { key: 'system', label: 'Системные' },
  { key: 'shared', label: 'Общие' },
  { key: 'custom', label: 'Свои' },
]

const { rows, meta, loading, error, load, goTo, reset } = usePaginated<CollectionRow>((page) =>
  api.listCollections({ search: search.value, type: typeFilter.value || undefined, page }),
)
onMounted(load)
function setType(t: '' | CollectionType) {
  typeFilter.value = t
  reset()
}

const columns: Column[] = [
  { key: 'title', label: 'Коллекция' },
  { key: 'type', label: 'Тип' },
  { key: 'source', label: 'Источник' },
  { key: 'owner', label: 'Владелец' },
  { key: 'items', label: 'Терминов', align: 'right', tnum: true },
  { key: 'created', label: 'Создана', align: 'right' },
]
function shortId(id: string): string {
  return id.slice(-6)
}
</script>

<template>
  <div>
    <PageHeader title="Коллекции">
      <template #actions>
        <div class="switch">
          <button
            v-for="t in TYPES"
            :key="t.key"
            :class="{ active: typeFilter === t.key }"
            @click="setType(t.key)"
          >
            {{ t.label }}
          </button>
        </div>
        <SearchInput v-model="search" placeholder="Поиск по названию" @search="reset" />
      </template>
    </PageHeader>

    <PaperCard :pad="false" class="wrap">
      <StateBlock v-if="loading && rows.length === 0" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
      <StateBlock v-else-if="rows.length === 0" kind="empty" title="Коллекций не найдено" />
      <template v-else>
        <DataTable
          :columns="columns"
          :rows="rows"
          :row-key="(c) => c.id"
          clickable
          @row-click="(c) => router.push({ name: 'collection', params: { id: c.id } })"
        >
          <template #cell-title="{ row }"><span class="serif name">{{ row.title }}</span></template>
          <template #cell-type="{ row }"><Badge>{{ COLLECTION_TYPE_LABEL[row.type] }}</Badge></template>
          <template #cell-source="{ row }"><Badge>{{ COLLECTION_SOURCE_LABEL[row.source] }}</Badge></template>
          <template #cell-owner="{ row }">
            <span v-if="row.ownerId" class="owner tnum">···{{ shortId(row.ownerId) }}</span>
            <span v-else class="faint">системная</span>
          </template>
          <template #cell-items="{ row }">{{ count(row.itemsCount) }}</template>
          <template #cell-created="{ row }"><RelativeDate :value="row.createdAt" /></template>
        </DataTable>
        <div class="pad"><Pagination :meta="meta" @change="goTo" /></div>
      </template>
    </PaperCard>
  </div>
</template>

<style scoped>
.wrap {
  overflow: hidden;
}
.pad {
  padding: 0 var(--s16) var(--s16);
}
.name {
  font-size: 16px;
}
.owner {
  font-size: 12.5px;
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
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 600;
  color: var(--secondary);
}
.switch button.active {
  background: var(--surface-raised);
  color: var(--ink);
  font-weight: 700;
  box-shadow: var(--shadow-card);
}
</style>
