<script setup lang="ts">
/**
 * One block of the plan page (ADM-1): a titled section with its anchor for the contents, its own
 * loading / error state, and the two things every block has — «JSON» (the endpoint's raw answer,
 * shown in place of the block's body) and «скачать .json» (the same answer as a file).
 */
import { ref } from 'vue'
import JsonBlock from '@/components/JsonBlock.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{
  id: string
  title: string
  subtitle?: string
  /** The endpoint's answer as it came — what «JSON» shows and «скачать» saves. */
  raw: unknown
  filename: string
  loading?: boolean
  error?: string | null
}>()
defineEmits<{ retry: [] }>()

const showJson = ref(false)

function download() {
  const blob = new Blob([JSON.stringify(props.raw, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = props.filename
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <section :id="id" class="block">
    <header class="block-head">
      <div>
        <h2 class="block-title serif">{{ title }}</h2>
        <p v-if="subtitle" class="block-sub muted">{{ subtitle }}</p>
      </div>
      <div class="block-actions">
        <slot name="actions" />
        <button class="lnk" :class="{ on: showJson }" :disabled="raw == null" @click="showJson = !showJson">JSON</button>
        <button class="lnk" :disabled="raw == null" @click="download">скачать .json</button>
      </div>
    </header>
    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="$emit('retry')" />
    <JsonBlock v-else-if="showJson" :title="filename" :value="raw" open />
    <div v-else-if="raw != null" class="block-body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.block {
  scroll-margin-top: var(--s22);
  padding: var(--s22) 0;
  border-top: 1px solid var(--hairline);
}
.block-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--s12);
  margin-bottom: var(--s16);
  flex-wrap: wrap;
}
.block-title {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.01em;
}
.block-sub {
  margin: 4px 0 0;
  font-size: 13px;
}
.block-actions {
  display: flex;
  align-items: center;
  gap: var(--s12);
  flex-wrap: wrap;
}
.lnk {
  border: 1px solid var(--hairline);
  background: transparent;
  border-radius: var(--r-pill);
  padding: 4px 11px;
  font-size: 12px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.lnk:hover:not(:disabled),
.lnk.on {
  color: var(--ink);
  border-color: var(--ink);
}
.lnk:disabled {
  opacity: 0.4;
  cursor: default;
}
.block-body {
  display: flex;
  flex-direction: column;
  gap: var(--s16);
}
</style>
