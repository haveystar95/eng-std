<script setup lang="ts">
// «Конвейер генерации» (ADM-1): the plan's job, then each day's ribbon — lesson (every attempt the
// journal holds) → validator → P2R → seam judge → photos → voice → served. Each stage: status, when,
// how long, model and prompt, tokens · $, its calls with the request and answer collapsed, and what
// the store does not keep.
import { toRef } from 'vue'
import type { PipelineStage, PlanPipeline } from '@/api/planTypes'
import StatusChip from '@/components/StatusChip.vue'
import { absoluteTime } from '@/utils/format'
import { duration, tokensBill, DAY_TYPE_LABEL } from '@/utils/planLabels'
import CallDetail from './CallDetail.vue'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanPipeline>(toRef(props, 'code'), 'pipeline', toRef(props, 'day'))

const FACT_LABEL: Record<string, string> = {
  model: 'модель',
  prompt_version: 'промт',
  build_version: 'сборка сервера',
  attempts: 'попыток',
  latency_ms: 'задержка',
  cost_usd: '$',
  cost_note: 'что в цене',
  fail_reason: 'причина',
  reason: 'причина',
  calls: 'вызовов',
  lines: 'строк',
  characters: 'символов',
  credits: 'кредитов',
  terms: 'слов',
  terms_with_photo: 'с фото',
  terms_without_photo: 'без фото',
  terms_not_searched: 'не искали',
  scene_image_url: 'фото сцены',
  scene_image_author: 'автор',
  scene_image_tone: 'тон',
  opened_at: 'открыт',
  first_served_at: 'впервые выдан',
  path: 'путь',
  fatal: 'фатальных',
  warnings: 'предупреждений',
}

const ISO = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/

/** The scalar facts of a stage, labelled; lists (findings, checks) are drawn on their own. */
function scalars(stage: PipelineStage): [string, string][] {
  return Object.entries(stage.facts)
    .filter(([, v]) => v === null || ['string', 'number', 'boolean'].includes(typeof v))
    .map(([k, v]) => [FACT_LABEL[k] ?? k, v === null ? 'н/д' : k === 'latency_ms' ? duration(Number(v)) : ISO.test(String(v)) ? absoluteTime(String(v)) : String(v)])
}

function findings(stage: PipelineStage): { code: string; address: string | null; detail: string | null; fatal: boolean }[] {
  const list = stage.facts.findings ?? stage.facts.rejected
  return Array.isArray(list) ? (list as { code: string; address: string | null; detail: string | null; fatal: boolean }[]) : []
}
</script>

<template>
  <PlanBlock
    id="pipeline"
    title="Конвейер генерации"
    subtitle="Job плана → урок → валидатор → P2R → судья швов → картинки → озвучка → выдан клиенту"
    :raw="data"
    :filename="jsonName(code, 'pipeline', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <div v-for="(group, gi) in [{ key: 'plan', label: 'План', stages: [data.plan_build], note: undefined as string | undefined }, ...data.days.map((d) => ({ key: `d${d.number}`, label: `День ${d.number} · ${DAY_TYPE_LABEL[d.type] ?? d.type}`, stages: d.stages, note: d.note }))]" :key="gi" class="ribbon">
        <h3 class="ribbon-title section-label">{{ group.label }}</h3>
        <p v-if="group.note" class="faint note">{{ group.note }}</p>
        <ol class="stages">
          <li v-for="stage in group.stages" :key="stage.key" class="stage">
            <div class="stage-head">
              <span class="stage-name">{{ stage.title }}</span>
              <StatusChip :status="stage.status" />
              <span class="when faint tnum">
                {{ stage.started_at ? absoluteTime(stage.started_at) : '—' }}
                <template v-if="stage.finished_at"> → {{ absoluteTime(stage.finished_at) }}</template>
                <template v-if="stage.duration_ms !== null"> · {{ duration(stage.duration_ms) }}</template>
              </span>
              <span v-if="stage.calls.length" class="bill tnum">{{ tokensBill(stage.tokens_in, stage.tokens_out, stage.calls_cost_usd) }}</span>
            </div>
            <dl v-if="scalars(stage).length" class="facts">
              <template v-for="[k, v] in scalars(stage)" :key="k">
                <dt>{{ k }}</dt>
                <dd>{{ v }}</dd>
              </template>
            </dl>
            <ul v-if="findings(stage).length" class="findings">
              <li v-for="(f, i) in findings(stage)" :key="i">
                <StatusChip :status="f.fatal ? 'error' : 'warning'" />
                <code>{{ f.code }}</code> <span class="faint">{{ f.address }}</span> — {{ f.detail }}
              </li>
            </ul>
            <table v-if="stage.calls.length" class="calls">
              <tbody>
                <tr v-for="c in stage.calls" :key="c.id" :class="{ bad: c.status !== 'completed' }">
                  <td><StatusChip :status="c.status" /></td>
                  <td class="tnum">{{ absoluteTime(c.started_at) }}</td>
                  <td>{{ c.purpose }} · {{ c.answered_model ?? c.model }}</td>
                  <td class="tnum">{{ duration(c.latency_ms) }}</td>
                  <td class="tnum">{{ tokensBill(c.tokens_in, c.tokens_out, c.cost_usd) }}</td>
                  <td>
                    <span v-if="!c.certain" class="warn" :title="`Окно пересекается с ${c.window.others} чужими сборками`">окно общее</span>
                    <code class="cid">{{ c.id }}</code>
                    <CallDetail :log-id="c.log_id" />
                  </td>
                </tr>
              </tbody>
            </table>
            <ul v-if="stage.not_stored.length" class="nd">
              <li v-for="(n, i) in stage.not_stored" :key="i">не хранится: {{ n }}</li>
            </ul>
          </li>
        </ol>
      </div>
    </template>
  </PlanBlock>
</template>

<style scoped>
.ribbon {
  display: flex;
  flex-direction: column;
  gap: var(--s8);
}
.note {
  margin: 0;
  font-size: 12.5px;
}
.stages {
  list-style: none;
  margin: 0;
  padding: 0 0 0 var(--s16);
  border-left: 2px solid var(--hairline);
  display: flex;
  flex-direction: column;
  gap: var(--s12);
}
.stage {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.stage::before {
  content: '';
  position: absolute;
  left: calc(-1 * var(--s16) - 6px);
  top: 6px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--paper);
  border: 2px solid var(--secondary);
}
.stage-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--s8);
}
.stage-name {
  font-weight: 700;
  font-size: 14px;
}
.when,
.bill {
  font-size: 12px;
}
.facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 12px;
  margin: 0;
  font-size: 12.5px;
}
.facts dt {
  color: var(--tertiary);
}
.facts dd {
  margin: 0;
  word-break: break-word;
}
.findings {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12.5px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.calls {
  border-collapse: collapse;
  font-size: 12px;
  width: 100%;
}
.calls td {
  padding: 4px 8px 4px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
}
.calls tr.bad td {
  color: var(--verdict-unknown);
}
.cid {
  font-size: 10.5px;
  color: var(--tertiary);
  margin-right: 6px;
}
.warn {
  color: var(--verdict-unsure);
  font-size: 11px;
  margin-right: 6px;
}
.nd {
  margin: 0;
  padding-left: 16px;
  font-size: 11.5px;
  color: var(--tertiary);
}
</style>
