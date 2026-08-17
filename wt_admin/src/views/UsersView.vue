<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { useInfinite } from '@/composables/useInfinite'
import { count } from '@/utils/format'
import { TIER_LABEL } from '@/utils/labels'
import type { UserRow } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import SearchInput from '@/components/SearchInput.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import InfiniteList from '@/components/InfiniteList.vue'

const router = useRouter()
const search = ref('')
const { rows, total, loading, loadingMore, error, done, reload, loadMore } = useInfinite<UserRow>((q) =>
  api.listUsers({ search: search.value, ...q }),
)

const columns: Column[] = [
  // No cap on the email column: it is the identity of the row, and an ellipsised email is a
  // column you have to click through to read.
  { key: 'email', label: 'Email / имя', width: '34%' },
  { key: 'tier', label: 'Тариф', width: '110px' },
  { key: 'cefr', label: 'CEFR', width: '80px' },
  { key: 'collections', label: 'Колл.', align: 'right', tnum: true, width: '90px' },
  { key: 'progress', label: 'В прогрессе', align: 'right', tnum: true, width: '120px' },
  { key: 'created', label: 'Регистрация', align: 'right', width: '150px' },
]

onMounted(reload)
function onSearch() {
  reload()
}
function open(u: UserRow) {
  router.push({ name: 'user', params: { id: u.id, tab: 'plan' } })
}
</script>

<template>
  <div>
    <PageHeader title="Пользователи">
      <template #actions>
        <SearchInput v-model="search" placeholder="Поиск по email или имени" @search="onSearch" />
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
        empty-message="Никого не найдено — измените запрос поиска."
        @more="loadMore"
        @retry="reload"
      >
        <DataTable
          :columns="columns"
          :rows="rows"
          :row-key="(u) => u.id"
          clickable
          sticky-header
          @row-click="open"
        >
          <template #cell-email="{ row }">
            <div class="email-cell">
              <span class="email">{{ row.email ?? '—' }}</span>
              <span class="name faint">{{ row.name }}</span>
            </div>
          </template>
          <template #cell-tier="{ row }">
            <Badge :tone="row.tier === 'premium' ? 'known' : 'neutral'">{{ TIER_LABEL[row.tier] }}</Badge>
          </template>
          <template #cell-cefr="{ row }">
            <span v-if="row.cefr" class="tnum">{{ row.cefr }}</span>
            <span v-else class="faint">—</span>
          </template>
          <template #cell-collections="{ row }">{{ count(row.collectionsCount) }}</template>
          <template #cell-progress="{ row }">{{ count(row.progressCount) }}</template>
          <template #cell-created="{ row }"><RelativeDate :value="row.createdAt" /></template>
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
.email-cell {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}
.email {
  font-weight: 600;
}
.name {
  font-size: 12px;
}
</style>
