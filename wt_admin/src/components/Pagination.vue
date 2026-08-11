<script setup lang="ts">
// Server-side pagination control. Shows the row range and prev/next; page numbers
// stay compact so it reads quietly under a table.
import { computed } from 'vue'
import type { PageMeta } from '@/api/types'
import PaperButton from './PaperButton.vue'

const props = defineProps<{ meta: PageMeta }>()
const emit = defineEmits<{ change: [page: number] }>()

const from = computed(() => (props.meta.total === 0 ? 0 : (props.meta.page - 1) * props.meta.perPage + 1))
const to = computed(() => Math.min(props.meta.page * props.meta.perPage, props.meta.total))

function go(page: number) {
  if (page >= 1 && page <= props.meta.totalPages && page !== props.meta.page) emit('change', page)
}
</script>

<template>
  <div class="pager">
    <div class="range faint tnum">
      {{ from }}–{{ to }} из {{ meta.total.toLocaleString('ru-RU') }}
    </div>
    <div class="controls">
      <PaperButton variant="ghost" small :disabled="meta.page <= 1" @click="go(meta.page - 1)">
        ← Назад
      </PaperButton>
      <span class="pageno tnum">{{ meta.page }} / {{ meta.totalPages }}</span>
      <PaperButton variant="ghost" small :disabled="meta.page >= meta.totalPages" @click="go(meta.page + 1)">
        Вперёд →
      </PaperButton>
    </div>
  </div>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s12);
  padding: var(--s12) var(--s4) 0;
  flex-wrap: wrap;
}
.range {
  font-size: 12.5px;
}
.controls {
  display: flex;
  align-items: center;
  gap: var(--s12);
}
.pageno {
  font-size: 12.5px;
  color: var(--secondary);
  min-width: 3ch;
  text-align: center;
}
</style>
