<script setup lang="ts">
import { ref } from 'vue'
import { api } from '@/api'
import type { Paginated, RequestLog } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import LogsPanel from '@/components/LogsPanel.vue'
import PaperButton from '@/components/PaperButton.vue'

const userId = ref('')
const status = ref('')
const path = ref('')
const panel = ref<InstanceType<typeof LogsPanel> | null>(null)

const fetcher = (page: number): Promise<Paginated<RequestLog>> =>
  api.listLogs({
    userId: userId.value || undefined,
    status: status.value ? Number(status.value) : undefined,
    path: path.value || undefined,
    page,
  })

function apply() {
  panel.value?.reset()
}
function clear() {
  userId.value = ''
  status.value = ''
  path.value = ''
  panel.value?.reset()
}
</script>

<template>
  <div>
    <PageHeader title="Логи" subtitle="Запросы к API — входящие и исходящие (тела не хранятся в этом логе)" />

    <div class="filter">
      <label class="f grow">
        <span class="section-label">ID пользователя</span>
        <input v-model="userId" type="text" placeholder="ULID" />
      </label>
      <label class="f grow">
        <span class="section-label">Путь</span>
        <input v-model="path" type="text" placeholder="api/v1/…" />
      </label>
      <label class="f">
        <span class="section-label">Статус</span>
        <input v-model="status" type="number" inputmode="numeric" placeholder="422" />
      </label>
      <PaperButton variant="quiet" small @click="apply">Применить</PaperButton>
      <PaperButton v-if="userId || status || path" variant="ghost" small @click="clear">Сбросить</PaperButton>
    </div>

    <LogsPanel ref="panel" :fetcher="fetcher" show-user />
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
.f input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
  height: 38px;
}
</style>
