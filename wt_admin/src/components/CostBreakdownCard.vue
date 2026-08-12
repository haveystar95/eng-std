<script setup lang="ts">
// Spend split by purpose. The `note` is rendered, not swallowed: a per-collection number that
// silently double-counts a shared term's enrichment is worse than no number at all.
import { money } from '@/utils/format'
import type { CostByPurpose } from '@/api/types'
import PaperCard from './PaperCard.vue'

defineProps<{ costs: CostByPurpose | null; title?: string; loading?: boolean }>()

const PURPOSE_LABEL: Record<string, string> = {
  generation: 'Генерация',
  images: 'Картинки',
  enrichment: 'Станок',
  realtime: 'Реалтайм',
  recap: 'Разбор',
  example_regen: 'Новый пример',
}
</script>

<template>
  <PaperCard class="costs">
    <div class="head">
      <h3 class="serif">{{ title ?? 'Стоимость' }}</h3>
      <span v-if="costs" class="total tnum">{{ money(costs.totalUsd) }}</span>
    </div>

    <p v-if="loading" class="faint">Считаем…</p>
    <template v-else-if="costs">
      <div class="rows">
        <div v-for="p in costs.byPurpose" :key="p.purpose" class="row" :class="{ zero: p.calls === 0 }">
          <span class="lbl">{{ PURPOSE_LABEL[p.purpose] ?? p.purpose }}</span>
          <span class="calls tnum">{{ p.calls }} выз.</span>
          <span class="tok tnum">
            <template v-if="p.tokensIn || p.tokensOut">{{ p.tokensIn }} / {{ p.tokensOut }}</template>
            <template v-else>—</template>
          </span>
          <span class="sum tnum">{{ p.costUsd ? money(p.costUsd) : '—' }}</span>
        </div>
      </div>
      <p v-if="costs.note" class="note">{{ costs.note }}</p>
    </template>
  </PaperCard>
</template>

<style scoped>
.head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: var(--s12);
}
.head h3 {
  margin: 0;
  font-size: 17px;
}
.total {
  font-size: 20px;
  font-weight: 800;
}
.rows {
  display: flex;
  flex-direction: column;
}
.row {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  gap: var(--s16);
  align-items: baseline;
  padding: 6px 0;
  border-bottom: 1px solid var(--divider-faint);
  font-size: 13.5px;
}
.row:last-child {
  border-bottom: none;
}
.row.zero {
  opacity: 0.45;
}
.calls,
.tok {
  font-size: 12px;
  color: var(--secondary);
}
.sum {
  font-weight: 700;
  min-width: 74px;
  text-align: right;
}
.note {
  margin: var(--s12) 0 0;
  font-size: 11.5px;
  line-height: 1.45;
  color: var(--secondary);
}
</style>
