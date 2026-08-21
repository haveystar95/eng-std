<script setup lang="ts">
/**
 * A collapsible JSON tree.
 *
 * Distinct from {@see JsonBlock.vue} on purpose, and the difference is what each is FOR. A logged
 * request body is read once and pasted somewhere, so it is a `<pre>`. A model's answer in the
 * sandbox is EXPLORED — «что оно вообще вернуло, где тут дистракторы, сколько их» — and a
 * pretty-printed wall of 200 lines answers none of those at a glance.
 *
 * Recursive: the component renders itself for every nested value. The first two levels open by
 * default, which is where the shape of an answer lives; deeper nodes stay folded so a long array
 * does not push everything else off the screen.
 */
import { computed } from 'vue'
import { ref } from 'vue'

const props = withDefaults(
  defineProps<{
    value: unknown
    /** The key this value sits under. Absent at the root. */
    label?: string
    depth?: number
  }>(),
  { depth: 0 },
)

const isArray = computed(() => Array.isArray(props.value))
const isObject = computed(
  () => typeof props.value === 'object' && props.value !== null && !Array.isArray(props.value),
)
const branch = computed(() => isArray.value || isObject.value)

const entries = computed<[string, unknown][]>(() => {
  if (isArray.value) return (props.value as unknown[]).map((v, i) => [String(i), v])
  if (isObject.value) return Object.entries(props.value as Record<string, unknown>)
  return []
})

/** `{3}` / `[12]` — the size, which is the one thing a folded node must still tell you. */
const summary = computed(() => (isArray.value ? `[${entries.value.length}]` : `{${entries.value.length}}`))

const open = ref(props.depth < 2)

const scalarClass = computed(() => {
  const v = props.value
  if (v === null) return 'null'
  if (typeof v === 'number') return 'num'
  if (typeof v === 'boolean') return 'bool'
  return 'str'
})

const scalarText = computed(() => {
  const v = props.value
  if (v === null) return 'null'
  if (typeof v === 'string') return `"${v}"`
  return String(v)
})
</script>

<template>
  <div class="node" :class="{ nested: depth > 0 }">
    <template v-if="branch">
      <button class="row toggle" :aria-expanded="open" @click="open = !open">
        <span class="caret" :class="{ open }" aria-hidden="true">▸</span>
        <span v-if="label !== undefined" class="key">{{ label }}</span>
        <span class="summary faint">{{ summary }}</span>
      </button>
      <div v-if="open" class="children">
        <JsonTree
          v-for="[key, child] in entries"
          :key="key"
          :value="child"
          :label="key"
          :depth="depth + 1"
        />
      </div>
    </template>

    <div v-else class="row leaf">
      <span v-if="label !== undefined" class="key">{{ label }}</span>
      <span class="value" :class="scalarClass">{{ scalarText }}</span>
    </div>
  </div>
</template>

<style scoped>
.node {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12.5px;
  line-height: 1.6;
}
.node.nested {
  padding-left: 14px;
  border-left: 1px solid var(--divider-faint);
}
.row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  width: 100%;
  text-align: left;
}
.toggle {
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  color: inherit;
  font: inherit;
}
.caret {
  display: inline-block;
  font-size: 9px;
  transition: transform 0.12s ease;
  color: var(--tertiary);
}
.caret.open {
  transform: rotate(90deg);
}
.key {
  color: var(--secondary);
}
.key::after {
  content: ':';
  color: var(--tertiary);
}
.summary {
  font-size: 11.5px;
}
.value.str {
  color: var(--ink);
}
.value.num,
.value.bool {
  color: var(--verdict-known);
}
.value.null {
  color: var(--tertiary);
}
.leaf {
  word-break: break-word;
}
</style>
