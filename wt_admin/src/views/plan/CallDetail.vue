<script setup lang="ts">
// A model call's request and answer, collapsed until asked for: the outbound log row the server
// matched to the call by its usage (`log_id`), read through the existing `/logs/{id}`. No row — «н/д».
import { ref } from 'vue'
import { api } from '@/api'
import type { RequestLogDetail } from '@/api/types'
import JsonBlock from '@/components/JsonBlock.vue'

const props = defineProps<{ logId: string | null }>()
const detail = ref<RequestLogDetail | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function load() {
  if (!props.logId || detail.value) return
  loading.value = true
  error.value = null
  try {
    detail.value = await api.getLog(props.logId)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не загрузилось'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <span v-if="!logId" class="faint na" title="В логе нет строки, совпадающей с вызовом по usage">запрос/ответ: н/д</span>
  <span v-else class="cd">
    <button v-if="!detail" class="lnk" :disabled="loading" @click="load">{{ loading ? '…' : 'запрос и ответ' }}</button>
    <span v-if="error" class="err">{{ error }}</span>
    <span v-if="detail" class="bodies">
      <JsonBlock title="Запрос модели" :value="detail.requestBody" />
      <JsonBlock title="Ответ модели" :value="detail.responseBody" />
    </span>
  </span>
</template>

<style scoped>
.na {
  font-size: 11.5px;
}
.lnk {
  border: none;
  background: transparent;
  padding: 0;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
  text-decoration: underline;
}
.err {
  color: var(--verdict-unknown);
  font-size: 11.5px;
}
.bodies {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 6px;
}
</style>
