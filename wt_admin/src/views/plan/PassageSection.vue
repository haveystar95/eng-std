<script setup lang="ts">
// «Прохождение» (ADM-1): card after card in the order they were answered — the kind, what the card
// expected, what came back (for speech — what the phone's recognition heard), the verdict and who
// gave it, the attempts, when — and the day's result as stored. Beside it: the client, as far as it
// says anything about itself (the User-Agent of its last call; no build, no device — «н/д»).
import { ref, toRef } from 'vue'
import type { PassageCard, PlanPassage } from '@/api/planTypes'
import JsonBlock from '@/components/JsonBlock.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StatusChip from '@/components/StatusChip.vue'
import { absoluteTime } from '@/utils/format'
import { DAY_TYPE_LABEL } from '@/utils/planLabels'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanPassage>(toRef(props, 'code'), 'passage', toRef(props, 'day'))
const open = ref<string | null>(null)

function answer(card: PassageCard): string {
  if (card.heard) return `«${card.heard}»`
  const a = card.answer ?? {}
  if (typeof a.choice === 'string') return `выбор ${a.choice}`
  if (typeof a.slot_value === 'string') return `окно: ${a.slot_value}`
  return card.answer ? JSON.stringify(card.answer) : '—'
}

function verdict(card: PassageCard): string {
  const j = card.judge
  if (!j) return 'клиент'
  const by = String(j.by ?? '')
  return `${by === 'model' ? 'судья-модель' : by === 'code' ? 'код' : by}${j.accepted === false ? ' · не принял' : ''}`
}
</script>

<template>
  <PlanBlock
    id="passage"
    title="Прохождение"
    subtitle="Хронология карточек, ответы ученика и итог дня"
    :raw="data"
    :filename="jsonName(code, 'passage', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <dl class="client">
        <dt>Клиент</dt>
        <dd>{{ data.client.user_agent ?? 'н/д' }}</dd>
        <dt>Последняя синхронизация</dt>
        <dd><RelativeDate v-if="data.client.last_sync_at" :value="data.client.last_sync_at" /><span v-else>н/д</span> <span class="faint">{{ data.client.last_path }}</span></dd>
        <dt>Версия сборки</dt>
        <dd>н/д</dd>
        <dt>Устройство</dt>
        <dd>н/д</dd>
      </dl>

      <article v-for="d in data.days" :key="d.number" class="day">
        <h3 class="day-title">
          День {{ d.number }} · {{ DAY_TYPE_LABEL[d.type] ?? d.type }} <StatusChip :status="d.status" />
        </h3>
        <p class="summary tnum">
          {{ d.summary.cards_done }}/{{ d.summary.cards_total }} карточек · {{ d.summary.minutes_spent }} мин
          · верно {{ d.summary.results.passed ?? 0 }} · с подсказкой {{ d.summary.results.hinted ?? 0 }} · ошибок {{ d.summary.results.failed ?? 0 }}
          · пропусков {{ d.summary.results.skipped ?? 0 }} · без ответа {{ d.summary.results.unanswered ?? 0 }}
          <template v-if="d.closed_at"> · закрыт {{ absoluteTime(d.closed_at) }}</template>
          <template v-if="d.summary.passed_at"> · итог записан {{ absoluteTime(d.summary.passed_at) }}</template>
          <span v-else-if="d.closed_at" class="warn"> · записи итога нет</span>
        </p>
        <p v-if="d.summary.stages_passed.length" class="faint stages">
          Этапы: <span v-for="s in d.summary.stages_passed" :key="s.stage">{{ s.stage }} {{ absoluteTime(s.passed_at) }}; </span>
        </p>
        <table v-if="d.cards.length" class="ct">
          <thead>
            <tr>
              <th>Время</th>
              <th>Этап · карточка</th>
              <th>Ожидалось</th>
              <th>Ответ ученика</th>
              <th>Вердикт</th>
              <th>Попыток</th>
              <th>Итог</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="c in d.cards" :key="c.id">
              <tr :class="c.result ?? 'unanswered'">
                <td class="tnum">{{ c.answered_at ? absoluteTime(c.answered_at) : '—' }}</td>
                <td>
                  {{ c.stage }} · <b>{{ c.kind }}</b>
                  <div class="faint">{{ c.unit_kind }} {{ c.unit_ref }}<template v-if="c.source === 'returned'"> · возврат из дня {{ c.from_day }}</template><template v-if="c.retry_of"> · повтор</template></div>
                </td>
                <td>{{ c.expected ?? '—' }}</td>
                <td>{{ answer(c) }}</td>
                <td>{{ verdict(c) }}</td>
                <td class="tnum">{{ c.attempts }}</td>
                <td><StatusChip :status="c.result ?? 'unanswered'" /></td>
                <td><button class="row-json" @click="open = open === c.id ? null : c.id">JSON</button></td>
              </tr>
              <tr v-if="open === c.id">
                <td colspan="8"><JsonBlock :title="`карточка ${c.id}`" :value="c" open /></td>
              </tr>
            </template>
          </tbody>
        </table>
        <p v-else class="faint">Карточек нет — день не раздан.</p>
      </article>

      <ul class="nd">
        <li v-for="(n, i) in data.not_stored" :key="i">не хранится: {{ n }}</li>
      </ul>
    </template>
  </PlanBlock>
</template>

<style scoped>
.client {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 12px;
  margin: 0;
  font-size: 13px;
}
.client dt {
  color: var(--tertiary);
}
.client dd {
  margin: 0;
}
.day {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.day-title {
  font-size: 16px;
  font-weight: 700;
  display: flex;
  gap: 8px;
  align-items: center;
}
.summary,
.stages {
  margin: 0;
  font-size: 12.5px;
}
.warn {
  color: var(--verdict-unsure);
}
.ct {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}
.ct th,
.ct td {
  text-align: left;
  padding: 5px 8px 5px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
}
.ct th {
  font-size: 11px;
  color: var(--tertiary);
}
.ct tr.failed td {
  color: var(--verdict-unknown);
}
.row-json {
  border: none;
  background: transparent;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.nd {
  margin: 0;
  padding-left: 16px;
  font-size: 11.5px;
  color: var(--tertiary);
}
</style>
