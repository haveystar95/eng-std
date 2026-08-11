<script setup lang="ts">
import { ref } from 'vue'
import { api } from '@/api'
import type { Generation, GenerationStatus, Paginated } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import GenerationsPanel from '@/components/GenerationsPanel.vue'
import PaperButton from '@/components/PaperButton.vue'

const userId = ref('')
const status = ref<'' | GenerationStatus>('')
const panel = ref<InstanceType<typeof GenerationsPanel> | null>(null)

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
    <PageHeader title="Генерации" subtitle="Запросы к ИИ на создание коллекций — стоимость и статус" />

    <div class="filter">
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
