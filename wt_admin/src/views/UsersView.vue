<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { count } from '@/utils/format'
import { TIER_LABEL } from '@/utils/labels'
import type { UserRow } from '@/api/types'
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
const { rows, meta, loading, error, load, goTo, reset } = usePaginated<UserRow>((page) =>
  api.listUsers({ search: search.value, page }),
)

const columns: Column[] = [
  { key: 'email', label: 'Email / имя' },
  { key: 'tier', label: 'Тариф' },
  { key: 'cefr', label: 'CEFR' },
  { key: 'collections', label: 'Колл.', align: 'right', tnum: true },
  { key: 'progress', label: 'В прогрессе', align: 'right', tnum: true },
  { key: 'created', label: 'Регистрация', align: 'right' },
]

onMounted(load)
function onSearch() {
  reset()
}
function open(u: UserRow) {
  router.push({ name: 'user', params: { id: u.id } })
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
      <StateBlock v-if="loading && rows.length === 0" kind="loading" />
      <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="load" />
      <StateBlock
        v-else-if="rows.length === 0"
        kind="empty"
        title="Никого не найдено"
        message="Измените запрос поиска."
      />
      <template v-else>
        <DataTable :columns="columns" :rows="rows" :row-key="(u) => u.id" clickable @row-click="open">
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
        <div class="pad">
          <Pagination :meta="meta" @change="goTo" />
        </div>
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
