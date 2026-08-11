<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { langLabel } from '@/utils/labels'
import type { TermRow } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import SearchInput from '@/components/SearchInput.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import Pagination from '@/components/Pagination.vue'
import StateBlock from '@/components/StateBlock.vue'

const router = useRouter()
const search = ref('')
const { rows, meta, loading, error, load, goTo, reset } = usePaginated<TermRow>((page) =>
  api.listTerms({ search: search.value, page }),
)
onMounted(load)

const columns: Column[] = [
  { key: 'image', label: 'Фото', width: '60px' },
  { key: 'text', label: 'Термин' },
  { key: 'lang', label: 'Язык' },
  { key: 'type', label: 'Тип' },
  { key: 'translation', label: 'Перевод' },
]
</script>

<template>
  <div>
    <PageHeader title="Термины">
      <template #actions>
        <SearchInput v-model="search" placeholder="Поиск по термину или переводу" @search="reset" />
      </template>
    </PageHeader>

    <PaperCard :pad="false" class="wrap">
      <StateBlock v-if="loading && rows.length === 0" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
      <StateBlock v-else-if="rows.length === 0" kind="empty" title="Терминов не найдено" />
      <template v-else>
        <DataTable
          :columns="columns"
          :rows="rows"
          :row-key="(t) => t.id"
          clickable
          @row-click="(t) => router.push({ name: 'term', params: { id: t.id } })"
        >
          <template #cell-image="{ row }"><ImageThumb :url="row.imageUrl" :size="44" :alt="row.text" /></template>
          <template #cell-text="{ row }"><span class="serif term">{{ row.text }}</span></template>
          <template #cell-lang="{ row }"><span class="faint">{{ langLabel(row.lang) }}</span></template>
          <template #cell-type="{ row }"><Badge>{{ row.type }}</Badge></template>
          <template #cell-translation="{ row }">
            <span class="muted">{{ row.translation ?? '—' }}</span>
          </template>
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
.term {
  font-size: 16px;
}
</style>
