<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { useInfinite } from '@/composables/useInfinite'
import { langLabel } from '@/utils/languages'
import type { TermRow } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import SearchInput from '@/components/SearchInput.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import InfiniteList from '@/components/InfiniteList.vue'

const router = useRouter()
const search = ref('')
const { rows, total, loading, loadingMore, error, done, reload, loadMore } = useInfinite<TermRow>((q) =>
  api.listTerms({ search: search.value, ...q }),
)
onMounted(reload)

const columns: Column[] = [
  { key: 'image', label: 'Фото', width: '60px' },
  { key: 'text', label: 'Термин', width: '28%' },
  { key: 'lang', label: 'Язык', width: '110px' },
  { key: 'type', label: 'Тип', width: '120px' },
  { key: 'translation', label: 'Перевод', truncate: '32ch' },
]
</script>

<template>
  <div>
    <PageHeader title="Термины">
      <template #actions>
        <SearchInput v-model="search" placeholder="Поиск по термину или переводу" @search="reload" />
      </template>
    </PageHeader>

    <PaperCard :pad="false" class="wrap">
      <InfiniteList
        :loading="loading"
        :loading-more="loadingMore"
        :error="error"
        :done="done"
        :count="rows.length"
        :total="total"
        empty-message="Терминов не найдено"
        @more="loadMore"
        @retry="reload"
      >
        <DataTable
          :columns="columns"
          :rows="rows"
          :row-key="(t) => t.id"
          clickable
          sticky-header
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
      </InfiniteList>
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
