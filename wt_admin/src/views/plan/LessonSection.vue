<script setup lang="ts">
// «Урок и голос» (ADM-1): the lesson as the day was dealt from it — scene, roles with gender,
// exchanges (frame, filler, in_dialogue), phrases, words, listening, checks, returns — and beside it
// every line the scene says: the voice its cast gives it (the pack's name + a short id), by which
// rule, the model, credits · characters · $, when bought, «послушать», and whether it is voiced.
import { computed, ref, toRef } from 'vue'
import type { LessonDay, PlanLesson, VoiceLineRow } from '@/api/planTypes'
import JsonBlock from '@/components/JsonBlock.vue'
import StatusChip from '@/components/StatusChip.vue'
import { absoluteTime } from '@/utils/format'
import { DAY_TYPE_LABEL, LINE_KIND_LABEL, voiceBill, voiceName } from '@/utils/planLabels'
import ListenButton from './ListenButton.vue'
import PlanBlock from './PlanBlock.vue'
import { jsonName, usePlanSection } from './usePlanSection'

const props = defineProps<{ code: string; day: number | null }>()
const { data, loading, error, run } = usePlanSection<PlanLesson>(toRef(props, 'code'), 'lesson', toRef(props, 'day'))

type Filter = 'all' | 'silent' | 'phone'
const filter = ref<Filter>('all')
const FILTERS: { key: Filter; label: string }[] = [
  { key: 'all', label: 'все строки' },
  { key: 'silent', label: 'без звука' },
  { key: 'phone', label: 'телефонный голос' },
]

function lines(day: LessonDay): VoiceLineRow[] {
  return day.voice.lines.filter((l) => filter.value === 'all' || (filter.value === 'phone' ? l.status === 'phone' : l.status !== 'voiced'))
}

interface Message {
  speaker?: string
  role_native?: string
  text_target?: string
  text_native?: string
  phrase_id?: string
  filler?: string
}
interface Exchange {
  step?: number
  kind?: string
  messages?: Message[]
  check?: { text_native?: string; options?: { text_target?: string; text_native?: string }[]; correct_option_index?: number }
}
interface Filler {
  target?: string
  native?: string
  in_dialogue?: boolean
}
interface Phrase {
  id?: string
  kind?: string
  frame_target?: string
  frame_native?: string
  slot?: { hint_native?: string; fillers?: Filler[] } | null
}
interface Word {
  id?: string
  kind?: string
  term_target?: string
  translation_native?: string
  used_in?: string[]
}

const asList = <T,>(v: unknown): T[] => (Array.isArray(v) ? (v as T[]) : [])
const exchanges = (d: LessonDay) => asList<Exchange>(d.lesson?.dialogue)
const phrases = (d: LessonDay) => asList<Phrase>(d.lesson?.phrases)
const words = (d: LessonDay) => asList<Word>(d.lesson?.vocabulary)

const days = computed(() => data.value?.days ?? [])
</script>

<template>
  <PlanBlock
    id="lesson"
    title="Урок и голос"
    subtitle="Урок в том виде, из которого раздан день, и озвучка каждой строки"
    :raw="data"
    :filename="jsonName(code, 'lesson', day)"
    :loading="loading"
    :error="error"
    @retry="run"
  >
    <template #actions>
      <span class="filters">
        <button v-for="f in FILTERS" :key="f.key" class="pill" :class="{ on: filter === f.key }" @click="filter = f.key">{{ f.label }}</button>
      </span>
    </template>
    <article v-for="d in days" :key="d.number" class="day">
      <h3 class="day-title">
        День {{ d.number }} · {{ DAY_TYPE_LABEL[d.type] ?? d.type }}
        <template v-if="d.scene"> · {{ d.scene.title_native }} <span class="faint">({{ d.scene.title_target }})</span></template>
      </h3>

      <template v-if="d.scene">
        <dl class="roles">
          <dt>Собеседник</dt>
          <dd>{{ d.scene.partner_role_native }} / {{ d.scene.partner_role_target }} · role_gender урока: <b>{{ d.scene.role_gender ?? 'пусто' }}</b> · голос: {{ d.scene.partner_voice_gender ?? 'н/д' }}</dd>
          <dt>Ученик</dt>
          <dd>{{ d.scene.learner_role_native }} / {{ d.scene.learner_role_target }} · пол профиля: <b>{{ d.scene.learner_voice_gender ?? 'не указан' }}</b></dd>
          <dt>Учит</dt>
          <dd>{{ d.scene.teaches_native }}</dd>
        </dl>

        <details class="part" open>
          <summary>Обмены · {{ exchanges(d).length }}</summary>
          <table class="lt">
            <tbody>
              <template v-for="(ex, i) in exchanges(d)" :key="i">
                <tr v-for="(m, j) in ex.messages ?? []" :key="`${i}-${j}`">
                  <td class="tnum faint">{{ j === 0 ? `x${ex.step}` : '' }}</td>
                  <td class="faint">{{ j === 0 ? ex.kind : '' }}</td>
                  <td>{{ m.role_native }}</td>
                  <td>
                    {{ m.text_target }}
                    <span v-if="m.phrase_id" class="mark">{{ m.phrase_id }}<template v-if="m.filler"> ← «{{ m.filler }}»</template></span>
                    <div class="faint">{{ m.text_native }}</div>
                  </td>
                </tr>
                <tr v-if="ex.check" class="check">
                  <td></td>
                  <td class="faint">проверка</td>
                  <td colspan="2">
                    {{ ex.check.text_native }}
                    <span v-for="(o, k) in ex.check.options ?? []" :key="k" class="opt" :class="{ right: k === ex.check.correct_option_index }">{{ o.text_target }}</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </details>

        <details class="part">
          <summary>Фразы · {{ phrases(d).length }}</summary>
          <ul class="plain">
            <li v-for="p in phrases(d)" :key="p.id">
              <code>{{ p.id }}</code> {{ p.frame_target }} <span class="faint">— {{ p.frame_native }} · {{ p.kind }}</span>
              <span v-for="(f, k) in p.slot?.fillers ?? []" :key="k" class="opt" :class="{ right: f.in_dialogue }" :title="f.in_dialogue ? 'in_dialogue' : ''">{{ f.target }}</span>
            </li>
          </ul>
        </details>

        <details class="part">
          <summary>Слова · {{ words(d).length }}</summary>
          <ul class="plain">
            <li v-for="w in words(d)" :key="w.id">
              <code>{{ w.id }}</code> {{ w.term_target }} <span class="faint">— {{ w.translation_native }} · {{ w.kind }} · в {{ (w.used_in ?? []).join(', ') || '—' }}</span>
            </li>
          </ul>
        </details>

        <JsonBlock title="Слушание (listening)" :value="d.lesson?.listening ?? null" />
      </template>
      <p v-else class="faint">Своего урока у дня нет — сцены: {{ d.scenes_covered.map((s) => `${s.title_native ?? s.id} (день ${s.day ?? '?'})`).join(', ') || '—' }}</p>

      <p v-if="d.returns.length" class="returns">
        Возвраты из прошлых дней:
        <span v-for="(r, i) in d.returns" :key="i" class="opt">{{ r.unit_kind }} {{ r.unit_ref }} · день {{ r.from_day ?? '?' }} · {{ r.cards }} карт.</span>
      </p>

      <div v-if="d.voice.lines.length" class="voice">
        <h4 class="section-label">Озвучка · озвучено {{ d.voice.voiced }} · телефон {{ d.voice.phone }} · нет голоса {{ d.voice.none }}</h4>
        <table class="vt">
          <thead>
            <tr>
              <th>Строка</th>
              <th>Текст</th>
              <th>Голос</th>
              <th>Правило</th>
              <th>Счёт</th>
              <th>Куплено</th>
              <th></th>
              <th>Статус</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in lines(d)" :key="l.ref" :class="l.status">
              <td><code>{{ l.ref }}</code><div class="faint">{{ LINE_KIND_LABEL[l.kind] }}</div></td>
              <td>
                {{ l.text }}
                <div v-if="l.voiced_text && l.voiced_text !== l.text" class="drift">озвучено: «{{ l.voiced_text }}»</div>
              </td>
              <td>
                {{ voiceName(l.voice) }}<div class="faint">{{ l.voice?.model ?? '' }}</div>
                <div v-for="(o, i) in l.other_voices" :key="i" class="faint">ещё: {{ voiceName(o.voice) }}</div>
              </td>
              <td class="rule">{{ l.rule }}</td>
              <td class="tnum">{{ l.audio ? voiceBill(l.audio.credits, l.audio.characters, l.audio.cost_usd) : '—' }}</td>
              <td class="tnum">{{ l.audio ? absoluteTime(l.audio.created_at) : '—' }}</td>
              <td><ListenButton v-if="l.audio" :code="code" :audio-id="l.audio.id" /></td>
              <td><StatusChip :status="l.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </PlanBlock>
</template>

<style scoped>
.filters {
  display: flex;
  gap: 6px;
}
.pill {
  border: 1px solid var(--hairline);
  background: transparent;
  border-radius: var(--r-pill);
  padding: 4px 10px;
  font-size: 12px;
  color: var(--secondary);
  cursor: pointer;
}
.pill.on {
  color: var(--ink);
  border-color: var(--ink);
  font-weight: 600;
}
.day {
  display: flex;
  flex-direction: column;
  gap: var(--s12);
  padding-bottom: var(--s16);
}
.day + .day {
  border-top: 1px dashed var(--hairline);
  padding-top: var(--s16);
}
.day-title {
  font-size: 16px;
  font-weight: 700;
}
.roles {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 12px;
  margin: 0;
  font-size: 13px;
}
.roles dt {
  color: var(--tertiary);
}
.roles dd {
  margin: 0;
}
.part summary {
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
}
.lt,
.vt {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}
.lt td,
.vt td,
.vt th {
  padding: 5px 8px 5px 0;
  border-top: 1px solid var(--hairline);
  vertical-align: top;
  text-align: left;
}
.vt th {
  font-size: 11px;
  color: var(--tertiary);
  font-weight: 600;
}
.vt tr.phone td {
  background: color-mix(in srgb, var(--verdict-unsure) 7%, transparent);
}
.mark {
  font-size: 11px;
  color: var(--tertiary);
  margin-left: 6px;
}
.opt {
  display: inline-block;
  margin: 2px 4px 0 0;
  padding: 1px 8px;
  border: 1px solid var(--hairline);
  border-radius: var(--r-pill);
  font-size: 11.5px;
}
.opt.right {
  border-color: var(--verdict-known);
  color: var(--verdict-known);
}
.plain {
  margin: 6px 0 0;
  padding-left: 0;
  list-style: none;
  font-size: 12.5px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.rule {
  font-size: 11.5px;
  color: var(--secondary);
  max-width: 220px;
}
.drift {
  color: var(--verdict-unknown);
  font-size: 11.5px;
}
.returns {
  font-size: 12.5px;
  margin: 0;
}
.voice h4 {
  margin: 0 0 6px;
}
.voice {
  overflow-x: auto;
}
</style>
