<script setup lang="ts">
// Relative time with the exact timestamp in a native tooltip (design quality rule).
import { computed } from 'vue'
import { absoluteTime, relativeTime } from '@/utils/format'

const props = defineProps<{ value: string | null | undefined }>()
const rel = computed(() => relativeTime(props.value))
const abs = computed(() => absoluteTime(props.value))
</script>

<template>
  <time class="reldate" :title="abs">{{ rel }}</time>
</template>

<style scoped>
.reldate {
  white-space: nowrap;
  cursor: default;
  border-bottom: 1px dotted var(--track);
}
</style>
