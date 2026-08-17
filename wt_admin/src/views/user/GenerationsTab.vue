<script setup lang="ts">
import { api } from '@/api'
import type { Generation, Paginated } from '@/api/types'
import GenerationsPanel from '@/components/GenerationsPanel.vue'

const props = defineProps<{ userId: string }>()

const fetcher = (page: number): Promise<Paginated<Generation>> =>
  api.listGenerations({ userId: props.userId, page })
</script>

<template>
  <GenerationsPanel :fetcher="fetcher" />
</template>
