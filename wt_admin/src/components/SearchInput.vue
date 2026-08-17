<script setup lang="ts">
// Debounced search field on white paper. Emits `search` after the user stops typing.
import { ref, watch } from 'vue'
import { Search } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{ modelValue?: string; placeholder?: string; debounce?: number }>(),
  { modelValue: '', placeholder: 'Поиск…', debounce: 250 },
)
const emit = defineEmits<{ 'update:modelValue': [v: string]; search: [v: string] }>()

const local = ref(props.modelValue)
let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => props.modelValue,
  (v) => {
    if (v !== local.value) local.value = v
  },
)

function onInput() {
  emit('update:modelValue', local.value)
  clearTimeout(timer)
  timer = setTimeout(() => emit('search', local.value.trim()), props.debounce)
}
</script>

<template>
  <label class="search">
    <Search class="icon" :size="16" aria-hidden="true" />
    <input v-model="local" type="search" :placeholder="placeholder" @input="onInput" />
  </label>
</template>

<style scoped>
.search {
  display: inline-flex;
  align-items: center;
  gap: var(--s8);
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 0 14px;
  height: 40px;
  min-width: 240px;
}
.search:focus-within {
  border-color: var(--tertiary);
}
.icon {
  color: var(--tertiary);
  flex-shrink: 0;
}
input {
  border: none;
  outline: none;
  background: transparent;
  width: 100%;
  font-size: 14px;
}
input::-webkit-search-cancel-button {
  cursor: pointer;
}
</style>
