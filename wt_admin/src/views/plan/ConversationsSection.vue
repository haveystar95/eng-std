<script setup lang="ts">
// «Разговоры» (ADM-1): every talk of the plan (the day's, the rehearsal's, «Ещё раз»). On top — the
// scene's constructions: said / partly / not, and on which turn; below — the transcript turn by
// turn: who, what, the sound, what recognition heard, «открывает конструкцию», the verdict, the
// delay and the money of the turn; and how the talk ended. «Скачать стенограмму .json».
import { ref, toRef } from 'vue'
import type { PlanTalk, PlanTalks } from '@/api/planTypes'
import StatusChip from '@/components/StatusChip.vue'
import { absoluteTime } from '@/utils/format'
import { duration, tokensBill, usdOrNa, voiceBill, voiceName, TALK_TYPE_LABEL } from '@/utils/planLabels'
import ListenButton from './ListenButton.vue'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanTalks>(toRef(props, 'code'), 'conversations', toRef(props, 'day'))
const expanded = ref<Record<string, boolean>>({})

function downloadTranscript(talk: PlanTalk) {
  const blob = new Blob([JSON.stringify(talk, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `plan-${props.code}-talk-${talk.id}.json`
  a.click()
  URL.revokeObjectURL(url)
}

/** Which construction a role line opened, by its ref. */
function opened(talk: PlanTalk, opensTarget: string | null): string | null {
  if (!opensTarget) return null
  const target = talk.targets.find((t) => opensTarget === `${t.scene_id}:${t.ref}` || opensTarget === t.ref)
  return target ? `${target.ref} «${target.frame_target}»` : opensTarget
}
</script>

<template>
  <PlanBlock
    id="conversations"
    title="Разговоры"
    subtitle="Каждый разговор: конструкции и стенограмма по ходам"
    :raw="data"
    :filename="jsonName(code, 'conversations', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template v-if="data">
      <p v-if="!data.data.length" class="faint">Разговоров не было.</p>
      <article v-for="t in data.data" :key="t.id" class="talk">
        <header class="talk-head">
          <button class="toggle" @click="expanded[t.id] = !expanded[t.id]">{{ expanded[t.id] ? '▾' : '▸' }}</button>
          <b>День {{ t.day }} · {{ TALK_TYPE_LABEL[t.type] ?? t.type }}</b>
          <StatusChip :status="t.state" />
          <span class="faint tnum">{{ absoluteTime(t.started_at) }} · {{ t.turns.length }} ходов из {{ t.turn_limit }} · {{ usdOrNa(t.cost_usd) }}</span>
          <span class="end" :class="{ warn: t.ended_reason === 'limit' }">{{ t.ended_label ?? 'идёт' }}</span>
          <span v-if="!t.openers_checked" class="faint">до 23.09 — открытия не проверялись</span>
          <button class="lnk" @click="downloadTranscript(t)">скачать стенограмму .json</button>
        </header>

        <ul class="targets">
          <li v-for="g in t.targets" :key="`${g.scene_id}:${g.ref}`">
            <StatusChip :status="g.status" />
            <code>{{ g.ref }}</code> {{ g.frame_target }} <span class="faint">— {{ g.frame_native }}</span>
            <span class="faint tnum">
              <template v-if="g.said_turn !== null"> · засчитана ходом {{ g.said_turn }}</template>
              <template v-else-if="g.status === 'partial'"> · ключевых слов {{ g.key_words.found }}/{{ g.key_words.total }} (ход {{ g.key_words.turn }})</template>
              <template v-if="g.opened_on_turn !== null"> · открыта ходом {{ g.opened_on_turn }}</template>
            </span>
          </li>
        </ul>

        <table v-if="expanded[t.id]" class="tt">
          <tbody>
            <tr v-for="turn in t.turns" :key="turn.index" :class="turn.speaker">
              <td class="tnum faint">{{ turn.index }}</td>
              <td class="who">{{ turn.speaker === 'partner' ? 'роль' : 'ученик' }}<div class="faint">{{ turn.kind }}</div></td>
              <td>
                {{ turn.text_target ?? '—' }}
                <div v-if="turn.text_native" class="faint">{{ turn.text_native }}</div>
                <div v-if="turn.speaker === 'learner'" class="faint">распознано: «{{ turn.heard ?? '' }}» · сырой текст распознавания: н/д</div>
                <div v-if="opened(t, turn.opens_target)" class="opens">открывает конструкцию {{ opened(t, turn.opens_target) }}</div>
                <div v-if="turn.hint_native" class="faint">подсказка: {{ turn.hint_native }}</div>
              </td>
              <td>
                <template v-if="turn.speaker === 'learner'">
                  <StatusChip v-if="turn.understood !== null" :status="turn.understood ? 'passed' : 'failed'" />
                  <span v-if="turn.off_topic" class="warn"> не по теме</span>
                  <div v-if="turn.phrases_used.length" class="faint">засчитано: {{ turn.phrases_used.join(', ') }}</div>
                </template>
                <template v-else-if="turn.audio">
                  {{ voiceName(turn.audio.voice) }}
                  <div v-if="turn.audio.expected_voice && turn.audio.expected_voice.key !== turn.audio.voice?.key" class="warn">по правилу: {{ voiceName(turn.audio.expected_voice) }}</div>
                  <div class="faint">{{ voiceBill(turn.audio.credits, turn.audio.characters, turn.audio.cost_usd) }}</div>
                  <ListenButton v-if="turn.audio_id" :code="code" :audio-id="turn.audio_id" />
                </template>
                <span v-else class="faint">без звука</span>
              </td>
              <td class="tnum">
                {{ duration(turn.latency_ms) }}
                <div v-if="turn.model" class="faint">{{ turn.model }} · {{ turn.prompt_version }}</div>
                <div v-if="turn.model" class="faint">{{ tokensBill(turn.tokens_in, turn.tokens_out, turn.model_cost_usd) }}</div>
                <div class="faint">ход {{ usdOrNa(turn.cost_usd) }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </article>
    </template>
  </PlanBlock>
</template>

<style scoped>
.talk {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: var(--s12);
  border-bottom: 1px dashed var(--hairline);
}
.talk-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
}
.toggle {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
}
.end {
  font-size: 12px;
  color: var(--secondary);
}
.warn {
  color: var(--verdict-unsure);
}
.lnk {
  border: none;
  background: transparent;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
  text-decoration: underline;
}
.targets {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12.5px;
}
.tt {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}
.tt td {
  padding: 6px 8px 6px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
}
.tt tr.learner td {
  background: var(--faint-ink);
}
.who {
  font-weight: 600;
  white-space: nowrap;
}
.opens {
  color: var(--verdict-known);
  font-size: 11.5px;
}
</style>
