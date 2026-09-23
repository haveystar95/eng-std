<script setup lang="ts">
// «Что не так» (ADM-1): the page's own findings, errors first, each pointing at its place — a day,
// a line, a card, a talk's turn, a model call. Empty — «план здоров».
import { toRef } from 'vue'
import type { PlanIssues } from '@/api/planTypes'
import StatusChip from '@/components/StatusChip.vue'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanIssues>(toRef(props, 'code'), 'issues', toRef(props, 'day'))

const PLACE: Record<string, string> = { day: 'день', line: 'строка', card: 'карточка', turn: 'ход', talk: 'разговор', call: 'вызов' }

/** Where on the page a finding's place is shown. */
function anchor(kind: string): string {
  return { line: '#lesson', card: '#passage', turn: '#conversations', talk: '#conversations', call: '#calls' }[kind] ?? '#days'
}
</script>

<template>
  <PlanBlock
    id="issues"
    title="Что не так"
    subtitle="Автопроверки по данным плана — только показывают, ничего не чинят"
    :raw="data"
    :filename="jsonName(code, 'issues', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <p v-if="data.healthy" class="healthy serif">План здоров — ни одна проверка ничего не нашла.</p>
      <ul v-else class="issues">
        <li v-for="(issue, i) in data.data" :key="i" class="issue" :class="issue.severity">
          <StatusChip :status="issue.severity" />
          <span class="where tnum">
            <a v-if="issue.place_kind === 'day'" :href="anchor(issue.place_kind)" class="place">{{ `день ${issue.place}` }}</a>
            <template v-else>
              {{ issue.day === null ? 'план' : `день ${issue.day}` }} · {{ PLACE[issue.place_kind] ?? issue.place_kind }}
              <a :href="anchor(issue.place_kind)" class="place">{{ issue.place }}</a>
            </template>
          </span>
          <span class="msg">{{ issue.message }}</span>
          <span class="check faint">{{ issue.title }}</span>
        </li>
      </ul>
      <ul v-if="data.notes?.length" class="notes">
        <li v-for="(n, i) in data.notes" :key="i" class="faint">
          {{ n.day === null ? 'план' : `день ${n.day}` }} · <a :href="anchor(n.place_kind)" class="place">{{ n.place }}</a> — {{ n.message }}
        </li>
      </ul>
      <div class="checks">
        <span v-for="c in data.checks" :key="c.code" class="check-count" :class="{ hit: c.count > 0 }">
          {{ c.title }} <b class="tnum">{{ c.count }}</b>
        </span>
      </div>
      <details class="nd">
        <summary>Чего проверки не видят</summary>
        <ul>
          <li v-for="(n, i) in data.not_checked" :key="i">{{ n }}</li>
        </ul>
      </details>
    </template>
  </PlanBlock>
</template>

<style scoped>
.healthy {
  font-size: 17px;
  color: var(--verdict-known);
  margin: 0;
}
.issues {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.issue {
  display: grid;
  grid-template-columns: 110px 190px 1fr auto;
  gap: var(--s12);
  align-items: baseline;
  padding: 8px 0;
  border-bottom: 1px solid var(--hairline);
  font-size: 13.5px;
}
.issue.error .msg {
  color: var(--verdict-unknown);
}
.where {
  color: var(--secondary);
  font-size: 12.5px;
}
.place {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11.5px;
  color: var(--ink);
  word-break: break-all;
}
.check {
  font-size: 11.5px;
  white-space: nowrap;
}
.notes {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 12.5px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.checks {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  font-size: 12px;
  color: var(--tertiary);
}
.check-count.hit {
  color: var(--ink);
}
.nd {
  font-size: 12.5px;
  color: var(--secondary);
}
.nd summary {
  cursor: pointer;
  font-weight: 600;
}
@media (max-width: 900px) {
  .issue {
    grid-template-columns: 1fr;
    gap: 4px;
  }
}
</style>
