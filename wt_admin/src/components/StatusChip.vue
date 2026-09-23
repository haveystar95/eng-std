<script setup lang="ts">
// The plan page's one chip for every status (ADM-1): the word and its tone come from planLabels,
// so a «failed» day, a «failed» card and a «failed» call look the same everywhere on the page.
import { computed } from 'vue'
import Badge from './Badge.vue'
import { chip } from '@/utils/planLabels'

const props = defineProps<{ status: string | null | undefined; solid?: boolean }>()
const c = computed(() => chip(props.status))
</script>

<template>
  <Badge :tone="c.tone" :solid="solid" :title="status ?? undefined">{{ c.label }}</Badge>
</template>
