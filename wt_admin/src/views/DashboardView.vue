<script setup lang="ts">
import { onMounted } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { count, money } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import StatCard from '@/components/StatCard.vue'
import StateBlock from '@/components/StateBlock.vue'

const { data, loading, error, run } = useAsync(() => api.dashboard())
onMounted(run)

const WINDOWS: { key: 'today' | 'last7d' | 'allTime'; label: string }[] = [
  { key: 'today', label: 'Сегодня' },
  { key: 'last7d', label: 'За 7 дней' },
  { key: 'allTime', label: 'За всё время' },
]
const CATEGORIES: { key: 'generation' | 'practice' | 'enrichment' | 'exampleRegen'; label: string }[] = [
  { key: 'generation', label: 'Генерация' },
  { key: 'practice', label: 'Практика' },
  { key: 'enrichment', label: 'Обогащение' },
  { key: 'exampleRegen', label: 'Регенерация примеров' },
]
</script>

<template>
  <div>
    <PageHeader title="Обзор" subtitle="Сводка по данным и расходам приложения" />

    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="data">
      <div class="section-label mb">Всего в системе</div>
      <div class="grid totals">
        <StatCard label="Пользователи" :value="count(data.totals.users)" />
        <StatCard label="Коллекции" :value="count(data.totals.collections)" />
        <StatCard label="Термины" :value="count(data.totals.terms)" />
        <StatCard label="Ревью сегодня" :value="count(data.totals.reviewsToday)" />
        <StatCard label="Ревью за 7 дней" :value="count(data.totals.reviews7d)" />
      </div>

      <div class="section-label mb mt">Расходы (USD)</div>
      <div class="grid spend">
        <PaperCard v-for="w in WINDOWS" :key="w.key" class="spend-card">
          <div class="spend-head">
            <div class="section-label">{{ w.label }}</div>
            <div class="spend-total tnum">{{ money(data.costs[w.key].total) }}</div>
          </div>
          <ul class="cats">
            <li v-for="c in CATEGORIES" :key="c.key">
              <span class="cat-name">{{ c.label }}</span>
              <span class="cat-amt tnum">{{ money(data.costs[w.key][c.key]) }}</span>
            </li>
          </ul>
        </PaperCard>
      </div>
    </template>
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  gap: var(--s16);
}
.totals {
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}
.spend {
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}
.mb {
  margin-bottom: var(--s12);
}
.mt {
  margin-top: var(--s26);
}
.spend-card {
  display: flex;
  flex-direction: column;
  gap: var(--s12);
}
.spend-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--s12);
}
.spend-total {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.cats {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.cats li {
  display: flex;
  justify-content: space-between;
  gap: var(--s12);
  font-size: 13.5px;
  padding: 4px 0;
  border-top: 1px solid var(--divider-faint);
}
.cat-name {
  color: var(--secondary);
}
.cat-amt {
  color: var(--ink);
}
</style>
