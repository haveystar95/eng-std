<script setup lang="ts">
// Collapsible pretty-printed JSON with a copy button. Deliberately a <pre> and not a tree widget:
// what you do with a logged request body is read it and paste it somewhere, and a tree makes both
// harder.
import { computed, ref } from 'vue'

const props = defineProps<{
  title: string
  value: unknown
  /** Start open. The response is what you usually came for; headers rarely. */
  open?: boolean
}>()

const expanded = ref(props.open ?? false)
const copied = ref(false)

const text = computed(() => {
  if (props.value === null || props.value === undefined) return ''
  try {
    return JSON.stringify(props.value, null, 2)
  } catch {
    return String(props.value)
  }
})
const empty = computed(() => text.value === '')
const lines = computed(() => (empty.value ? 0 : text.value.split('\n').length))

async function copy() {
  try {
    await navigator.clipboard.writeText(text.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1200)
  } catch {
    // Clipboard can be blocked (no permission, insecure origin) — the text is on screen anyway.
  }
}
</script>

<template>
  <div class="jb">
    <div class="jb-head">
      <button class="jb-toggle" :aria-expanded="expanded" @click="expanded = !expanded">
        <span class="caret" :class="{ open: expanded }" aria-hidden="true">▸</span>
        <span class="jb-title">{{ title }}</span>
        <span v-if="empty" class="faint">— пусто</span>
        <span v-else class="faint tnum">{{ lines }} стр.</span>
      </button>
      <button v-if="!empty" class="jb-copy" @click="copy">{{ copied ? 'Скопировано' : 'Копировать' }}</button>
    </div>
    <pre v-if="expanded && !empty" class="jb-body">{{ text }}</pre>
  </div>
</template>

<style scoped>
.jb {
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  overflow: hidden;
}
.jb-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s12);
  padding: 6px 10px;
  background: var(--faint-ink);
}
.jb-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}
.caret {
  display: inline-block;
  transition: transform 0.12s ease;
  font-size: 10px;
}
.caret.open {
  transform: rotate(90deg);
}
.jb-copy {
  border: none;
  background: transparent;
  font-size: 11.5px;
  color: var(--secondary);
  cursor: pointer;
}
.jb-copy:hover {
  color: var(--ink);
  text-decoration: underline;
}
.jb-body {
  margin: 0;
  padding: 10px 12px;
  font-size: 12px;
  line-height: 1.5;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  white-space: pre;
  overflow-x: auto;
  max-height: 460px;
  overflow-y: auto;
}
</style>
