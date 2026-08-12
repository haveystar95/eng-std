<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import type { CostByPurpose, Generation, GenerationStatus, Paginated } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import GenerationsPanel from '@/components/GenerationsPanel.vue'
import PaperButton from '@/components/PaperButton.vue'
import CostBreakdownCard from '@/components/CostBreakdownCard.vue'

const userId = ref('')
const status = ref<'' | GenerationStatus>('')
const panel = ref<InstanceType<typeof GenerationsPanel> | null>(null)

// The period total sits ABOVE the list: the first question on this screen is "how much did the
// last week cost", not "what was the 40th generation".
const PERIODS: { key: 'day' | 'week' | 'month' | 'all'; label: string }[] = [
  { key: 'day', label: 'Сутки' },
  { key: 'week', label: 'Неделя' },
  { key: 'month', label: 'Месяц' },
  { key: 'all', label: 'Всё время' },
]
const period = ref<'day' | 'week' | 'month' | 'all'>('week')
const costs = ref<CostByPurpose | null>(null)
const costsLoading = ref(false)

async function loadCosts() {
  costsLoading.value = true
  try {
    costs.value = await api.getCosts(period.value)
  } catch {
    costs.value = null
  } finally {
    costsLoading.value = false
  }
}
onMounted(loadCosts)
watch(period, loadCosts)

const fetcher = (page: number): Promise<Paginated<Generation>> =>
  api.listGenerations({
    userId: userId.value || undefined,
    status: status.value || undefined,
    page,
  })

function apply() {
  panel.value?.reset()
}
function clear() {
  userId.value = ''
  status.value = ''
  panel.value?.reset()
}
</script>

<template>
  <div>
    <PageHeader title="Генерации" subtitle="Запросы к ИИ на создание коллекций — стоимость и статус">
      <template #actions>
        <div class="switch">
          <button
            v-for="p in PERIODS"
            :key="p.key"
            :class="{ active: period === p.key }"
            @click="period = p.key"
          >
            {{ p.label }}
          </button>
        </div>
      </template>
    </PageHeader>

    <CostBreakdownCard :costs="costs" :loading="costsLoading" title="Расходы за период" />

    <div class="filter mt">
      <label class="f grow">
        <span class="section-label">ID пользователя</span>
        <input v-model="userId" type="text" placeholder="ULID" />
      </label>
      <label class="f">
        <span class="section-label">Статус</span>
        <select v-model="status">
          <option value="">все</option>
          <option value="pending">в очереди</option>
          <option value="running">выполняется</option>
          <option value="succeeded">успех</option>
          <option value="failed">ошибка</option>
        </select>
      </label>
      <PaperButton variant="quiet" small @click="apply">Применить</PaperButton>
      <PaperButton v-if="userId || status" variant="ghost" small @click="clear">Сбросить</PaperButton>
    </div>

    <GenerationsPanel ref="panel" :fetcher="fetcher" show-user />
  </div>
</template>

<style scoped>
.filter {
  display: flex;
  align-items: flex-end;
  gap: var(--s12);
  margin-bottom: var(--s16);
  flex-wrap: wrap;
}
.mt {
  margin-top: var(--s16);
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
  padding: 6px 12px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.switch button.active {
  background: var(--surface-raised);
  color: var(--ink);
  font-weight: 700;
  box-shadow: var(--shadow-card);
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f.grow {
  flex: 1;
  min-width: 180px;
}
.f input,
.f select {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
  height: 38px;
}
</style>
