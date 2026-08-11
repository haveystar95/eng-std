<script setup lang="ts">
import { ref } from 'vue'
import { api } from '@/api'
import type { Paginated, RequestLog } from '@/api/types'
import LogsPanel from '@/components/LogsPanel.vue'
import PaperButton from '@/components/PaperButton.vue'

const props = defineProps<{ userId: string }>()

const status = ref('')
const path = ref('')
const panel = ref<InstanceType<typeof LogsPanel> | null>(null)

const fetcher = (page: number): Promise<Paginated<RequestLog>> =>
  api.listLogs({
    userId: props.userId,
    status: status.value ? Number(status.value) : undefined,
    path: path.value || undefined,
    page,
  })

function apply() {
  panel.value?.reset()
}
function clear() {
  status.value = ''
  path.value = ''
  panel.value?.reset()
}
</script>

<template>
  <div>
    <div class="filter">
      <label class="f">
        <span class="section-label">Статус</span>
        <input v-model="status" type="number" inputmode="numeric" placeholder="напр. 422" />
      </label>
      <label class="f grow">
        <span class="section-label">Путь</span>
        <input v-model="path" type="text" placeholder="api/v1/…" />
      </label>
      <PaperButton variant="quiet" small @click="apply">Применить</PaperButton>
      <PaperButton v-if="status || path" variant="ghost" small @click="clear">Сбросить</PaperButton>
    </div>
    <LogsPanel ref="panel" :fetcher="fetcher" />
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
  min-width: 200px;
}
.f input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
}
</style>
