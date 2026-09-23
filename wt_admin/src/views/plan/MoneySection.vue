<script setup lang="ts">
// «Деньги» (ADM-1): by stage × by day — generation (lesson / P2R / seam judge when the journal can
// say them), photos, voice (credits · characters · $), talks (model and voice by turn), slot judges —
// the plan's total, and each day against the canon: ≈ $0.16 = generation ≈ $0.08 + voice ≈ $0.08,
// repairs ≤ 10 %.
import { toRef } from 'vue'
import type { PlanMoney } from '@/api/planTypes'
import { percent } from '@/utils/format'
import { tokensBill, usdOrNa, voiceBill, DAY_TYPE_LABEL } from '@/utils/planLabels'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanMoney>(toRef(props, 'code'), 'money', toRef(props, 'day'))
</script>

<template>
  <PlanBlock
    id="money"
    title="Деньги"
    subtitle="По этапам и дням; модели — токены туда/обратно · $, голос — кредиты · символы · $"
    :raw="data"
    :filename="jsonName(code, 'money', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <p class="canon">
        Канон дня: {{ usdOrNa(data.canon.day_usd) }} = генерация ≈ {{ usdOrNa(data.canon.generation_usd) }} + озвучка ≈ {{ usdOrNa(data.canon.voice_usd) }};
        починки ≤ {{ percent(data.canon.repair_share) }} цены генерации.
      </p>
      <div class="table-wrap">
        <table class="mt">
          <thead>
            <tr>
              <th>День</th>
              <th>Генерация (урок · P2R · судья)</th>
              <th>Фото</th>
              <th>Голос</th>
              <th>Разговор</th>
              <th>Судья слотов</th>
              <th>Сборка дня</th>
              <th>Всего</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="data.plan_build.included">
              <td>Job плана</td>
              <td class="tnum">{{ tokensBill(data.plan_build.tokens_in, data.plan_build.tokens_out, data.plan_build.cost_usd) }}<div class="faint">{{ data.plan_build.model }} · попыток {{ data.plan_build.attempts ?? 'н/д' }}</div></td>
              <td>—</td>
              <td>—</td>
              <td>—</td>
              <td>—</td>
              <td>—</td>
              <td class="tnum">{{ usdOrNa(data.plan_build.cost_usd) }}</td>
            </tr>
            <tr v-for="d in data.days" :key="d.number" :class="{ over: d.over_canon }">
              <td>{{ d.number }} · {{ DAY_TYPE_LABEL[d.type] ?? d.type }}</td>
              <td class="tnum">
                <template v-if="d.generation">
                  {{ usdOrNa(d.generation.cost_usd) }}
                  <div class="faint">
                    урок {{ usdOrNa(d.generation.lesson_usd) }} · P2R {{ usdOrNa(d.generation.repair_usd) }} · судья {{ usdOrNa(d.generation.judge_usd) }}
                  </div>
                  <div class="faint">{{ tokensBill(d.generation.tokens_in, d.generation.tokens_out, null).replace(' · н/д', '') }} · вызовов {{ d.generation.calls }}<template v-if="!d.generation.certain"> · разбивка н/д</template></div>
                  <div v-if="d.repair_share !== null" class="faint" :class="{ warn: d.repair_share > data.canon.repair_share }">P2R {{ percent(d.repair_share) }}</div>
                </template>
                <span v-else class="faint">—</span>
              </td>
              <td class="faint">н/д</td>
              <td class="tnum">{{ voiceBill(d.voice.credits, d.voice.characters, d.voice.cost_usd) }}<div class="faint">{{ d.voice.lines }} строк</div></td>
              <td class="tnum">
                {{ usdOrNa(d.conversation.total_usd) }}
                <div class="faint">модель {{ tokensBill(d.conversation.tokens_in, d.conversation.tokens_out, d.conversation.model_usd) }}</div>
                <div class="faint">голос {{ voiceBill(d.conversation.credits, d.conversation.characters, d.conversation.speech_usd) }}</div>
                <div class="faint">распознавание н/д</div>
              </td>
              <td class="tnum">{{ tokensBill(d.slot_judge.tokens_in, d.slot_judge.tokens_out, d.slot_judge.cost_usd) }}<div class="faint">{{ d.slot_judge.calls }} вызовов</div></td>
              <td class="tnum" :class="{ warn: d.over_canon }">{{ usdOrNa(d.build_usd) }}</td>
              <td class="tnum"><b>{{ usdOrNa(d.total_usd) }}</b></td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td>Итого</td>
              <td class="tnum">{{ usdOrNa(data.totals.generation_usd) }}</td>
              <td class="faint">н/д</td>
              <td class="tnum">{{ voiceBill(data.totals.voice.credits, data.totals.voice.characters, data.totals.voice.cost_usd) }}</td>
              <td class="tnum">{{ usdOrNa(data.totals.conversation.total_usd) }}</td>
              <td class="tnum">{{ usdOrNa(data.totals.slot_judge.cost_usd) }}</td>
              <td></td>
              <td class="tnum"><b>{{ usdOrNa(data.totals.total_usd) }}</b></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <ul class="nd">
        <li v-for="(n, i) in data.not_stored" :key="i">не хранится: {{ n }}</li>
      </ul>
    </template>
  </PlanBlock>
</template>

<style scoped>
.canon {
  margin: 0;
  font-size: 13px;
}
.table-wrap {
  overflow-x: auto;
}
.mt {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}
.mt th,
.mt td {
  text-align: left;
  padding: 6px 10px 6px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
}
.mt th {
  font-size: 11px;
  color: var(--tertiary);
}
.mt tfoot td {
  border-top: 2px solid var(--ink);
  font-weight: 600;
}
.warn,
.over td:first-child {
  color: var(--verdict-unsure);
}
.nd {
  margin: 0;
  padding-left: 16px;
  font-size: 11.5px;
  color: var(--tertiary);
}
</style>
