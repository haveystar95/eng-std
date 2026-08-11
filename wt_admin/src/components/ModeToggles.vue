<script setup lang="ts">
// The toggle list itself, shared by the global screen and the per-user block.
//
// Order matters and is preserved: free practice round-robins by index into this list, on the
// server and mirrored on the device. So the checkboxes render in `available` order and the value
// is emitted in that same order — never as a set.
import { computed } from 'vue'
import { MODE_LABEL } from '@/utils/labels'
import type { ExerciseMode } from '@/api/types'

const props = defineProps<{
  available: ExerciseMode[]
  modelValue: ExerciseMode[]
  disabled?: boolean
}>()
const emit = defineEmits<{ 'update:modelValue': [ExerciseMode[]] }>()

const selected = computed(() => new Set(props.modelValue))

function toggle(mode: ExerciseMode) {
  if (props.disabled) return
  const next = props.available.filter((m) => (m === mode ? !selected.value.has(m) : selected.value.has(m)))
  emit('update:modelValue', next)
}
</script>

<template>
  <ul class="modes">
    <li v-for="mode in available" :key="mode">
      <label class="row" :class="{ off: !selected.has(mode), disabled }">
        <input
          type="checkbox"
          :checked="selected.has(mode)"
          :disabled="disabled"
          @change="toggle(mode)"
        />
        <span class="box" aria-hidden="true">
          <span v-if="selected.has(mode)" class="tick">✓</span>
        </span>
        <span class="name">{{ MODE_LABEL[mode] }}</span>
        <span class="wire faint tnum">{{ mode }}</span>
      </label>
    </li>
  </ul>
</template>

<style scoped>
.modes {
  list-style: none;
  margin: 0;
  padding: 0;
}
.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 2px;
  border-bottom: 1px solid var(--hairline);
  cursor: pointer;
  user-select: none;
}
.modes li:last-child .row {
  border-bottom: none;
}
.row.disabled {
  cursor: default;
  opacity: 0.55;
}
/* Native input kept for keyboard + a11y, drawn by the paper box next to it. */
.row input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.box {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
  border: 1.5px solid var(--ink);
  border-radius: 6px;
  display: grid;
  place-items: center;
  background: var(--paper);
  transition: background 120ms ease, border-color 120ms ease;
}
.row.off .box {
  border-color: var(--tertiary);
}
.row input:focus-visible + .box {
  outline: 2px solid var(--ink);
  outline-offset: 2px;
}
.tick {
  color: var(--ink);
  font-size: 13px;
  line-height: 1;
}
.name {
  font-weight: 600;
}
.row.off .name {
  color: var(--secondary);
  font-weight: 500;
}
.wire {
  margin-left: auto;
  font-size: 12px;
}
</style>
