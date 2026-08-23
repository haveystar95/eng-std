<script setup lang="ts">
/**
 * «Паспорт контента» одного термина: что у него ЕСТЬ и что из этого соберётся в карточку.
 *
 * Один вопрос и ровно один: **что позволяет контент термина**. Он НЕ отвечает, когда тренажёр
 * откроется ученику — это вопрос лестницы усвоения, у неё свой экран («Прогресс обучения»), и
 * смешивать их нельзя в обе стороны: зелёная строка здесь не значит, что ученик увидит карточку,
 * а красная не значит, что виноват прогресс.
 *
 * Отдельный компонент, потому что живёт в двух местах: в разделе «Контент» (третий уровень) и
 * свёрнутой секцией в CRUD-карточке термина. Данные грузятся при монтировании — карточка термина
 * монтирует его только по развороту, и это и есть ленивая загрузка.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import {
  CONTENT_GAP_LABEL,
  CONTENT_STATUS_LABEL,
  NEEDS_ENRICHMENT_REASON_LABEL,
  contentStatusTone,
  modeLabel,
} from '@/utils/labels'
import { langLabel } from '@/utils/languages'
import type { PassportDistractor, TermContentPassport } from '@/api/types'
import Badge from '@/components/Badge.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = withDefaults(
  defineProps<{
    termId: string
    /**
     * Hidden when the passport is already INSIDE the term card — the link would point at itself.
     * When shown it carries `#health`, so the trip lands on the card's own section rather than at
     * the top of the page.
     */
    showTermLink?: boolean
  }>(),
  { showTermLink: true },
)

const data = ref<TermContentPassport | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    data.value = await api.getTermContentPassport(props.termId)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.termId, load)
defineExpose({ reload: load })

const ERROR_TYPE_LABEL: Record<string, string> = {
  article: 'артикль',
  preposition: 'предлог',
  tense: 'время',
  word_order: 'порядок слов',
  false_friend: 'ложный друг',
  modal_to: 'модальный + to',
}

const usable = computed(() => data.value?.distractors.filter((d) => d.usable) ?? [])
const unusable = computed(() => data.value?.distractors.filter((d) => !d.usable) ?? [])

/**
 * Split a distractor sentence around its `error_span` so the wrong bit is marked in place. Falls
 * back to the whole sentence when the span isn't found verbatim — better plain than mangled.
 */
function splitSpan(sentence: string, span: string): [string, string, string] {
  const at = sentence.indexOf(span)
  if (at === -1) return [sentence, '', '']
  return [sentence.slice(0, at), span, sentence.slice(at + span.length)]
}

function distractorKey(d: PassportDistractor): string {
  return d.id
}

// ── Copying the догон line ──
// The panel never RUNS the станок: it spends real money against a model, and an admin screen is
// not the place to authorise that. It hands over the command; the operator reads it and pastes it.
const copied = ref(false)
async function copyCommand(): Promise<void> {
  const text = data.value?.topupCommand
  if (!text) return
  try {
    await navigator.clipboard?.writeText(text)
    copied.value = true
    setTimeout(() => (copied.value = false), 1600)
  } catch {
    // A browser that refuses the clipboard is not an error worth a red box — the command is on
    // screen and selectable, which is the fallback that always works.
  }
}
</script>

<template>
  <div class="passport">
    <StateBlock v-if="loading && !data" kind="loading" />
    <StateBlock v-else-if="error && !data" kind="error" :message="error" retryable @retry="load" />

    <template v-else-if="data">
      <p class="scope-note faint">
        Здесь — <strong>что позволяет контент термина</strong>: соберётся ли карточка из его текста,
        примера, перевода примера и дистракторов. Когда тренажёр <em>откроется ученику</em> — другой
        вопрос, он на экране
        <RouterLink :to="{ name: 'ladder' }">«Прогресс обучения»</RouterLink>.
      </p>

      <!-- ── Контент ─────────────────────────────────────────────────────────────────────── -->
      <PaperCard class="block">
        <h3 class="serif">Контент</h3>
        <div class="kv">
          <span class="section-label">Термин</span>
          <span class="serif big">{{ data.text }}</span>
        </div>
        <div class="kv">
          <span class="section-label">Переводы</span>
          <ul class="inline-list">
            <li v-for="t in data.translations" :key="t.lang + t.text">
              <span class="faint lang">{{ langLabel(t.lang) }}</span>
              {{ t.text }}
              <Badge v-if="t.isPrimary" tone="known">основной</Badge>
            </li>
            <li v-if="data.translations.length === 0" class="faint">переводов нет</li>
          </ul>
        </div>
        <div class="kv">
          <span class="section-label">Закреплённый пример</span>
          <div v-if="data.example">
            <p class="sentence serif">{{ data.example.sentence }}</p>
            <p v-if="data.example.translation" class="muted tr">{{ data.example.translation }}</p>
            <p v-else class="warn">Перевода примера нет — «предложение» и «верное предложение» без него не спросят.</p>
          </div>
          <p v-else class="warn">
            Примера нет. Это лечится <strong>перегенерацией примера</strong>, а не станком — станку не
            за что зацепиться.
          </p>
        </div>
        <p class="faint versions">
          Станок: {{ data.enrichmentVersion ?? 'не прогонялся' }} · текущая версия
          {{ data.currentGeneratorVersion }}
          <template v-if="data.enrichmentVersions.length > 1">
            · отметок: {{ data.enrichmentVersions.length }}
          </template>
        </p>
      </PaperCard>

      <!-- ── Дистракторы ─────────────────────────────────────────────────────────────────── -->
      <PaperCard class="block">
        <h3 class="serif">Дистракторы «найди ошибку»</h3>
        <p class="faint sub">
          Пригодных {{ data.usableDistractors }} из {{ data.distractors.length }} строк. Карточка берёт
          <strong>по одной на фрагмент ошибки</strong>: два варианта, сломанных в одном месте,
          превращают вопрос «какое предложение верное» в «какое написание мы имели в виду».
        </p>
        <p v-if="data.distractors.length === 0" class="faint">Дистракторов нет.</p>

        <div v-for="d in usable" :key="distractorKey(d)" class="distractor">
          <p class="d-sentence">
            <template v-for="(part, i) in [splitSpan(d.sentence, d.errorSpan)]" :key="i">
              {{ part[0] }}<mark v-if="part[1]">{{ part[1] }}</mark>{{ part[2] }}
            </template>
          </p>
          <p class="d-meta faint">
            <Badge>{{ ERROR_TYPE_LABEL[d.errorType] ?? d.errorType }}</Badge>
            → <span class="correction">{{ d.correction }}</span>
            <span class="ver">{{ d.generatorVersion }}</span>
          </p>
        </div>

        <div v-if="unusable.length" class="sub-section">
          <div class="section-label">Не пойдут в карточку — фрагмент ошибки уже занят</div>
          <div v-for="d in unusable" :key="distractorKey(d)" class="distractor dim">
            <p class="d-sentence">
              <template v-for="(part, i) in [splitSpan(d.sentence, d.errorSpan)]" :key="i">
                {{ part[0] }}<mark v-if="part[1]">{{ part[1] }}</mark>{{ part[2] }}
              </template>
            </p>
            <p class="d-meta faint">
              <Badge>{{ ERROR_TYPE_LABEL[d.errorType] ?? d.errorType }}</Badge>
              → <span class="correction">{{ d.correction }}</span>
            </p>
          </div>
        </div>

        <div v-if="data.suppressed.length" class="sub-section suppressed">
          <div class="section-label">Подавленные — их выкинул человек или аудит</div>
          <p class="faint tiny">
            Строка подавления переживает и сам дистрактор, и пример, к которому он крепился: догон
            больше не предложит это предложение заново.
          </p>
          <ul class="supp-list">
            <li v-for="(s, i) in data.suppressed" :key="i">
              <span class="supp-sentence">{{ s.sentence }}</span>
              <Badge tone="unsure">{{ s.source === 'review' ? 'вычитка' : 'аудит' }}</Badge>
            </li>
          </ul>
        </div>

        <p class="faint tiny note">{{ data.errorTypeNote }}</p>
      </PaperCard>

      <!-- ── Варианты ────────────────────────────────────────────────────────────────────── -->
      <PaperCard class="block">
        <h3 class="serif">Принимаемые варианты</h3>
        <p class="faint sub">Эти написания сервер (и клиент офлайн) засчитывают как верный ответ.</p>
        <p v-if="data.acceptedVariants.length === 0" class="faint">Вариантов нет — ответ принимается только сам термин.</p>
        <ul v-else class="variants">
          <li v-for="v in data.acceptedVariants" :key="v.text">
            <span class="serif">{{ v.text }}</span>
            <span v-if="v.note" class="muted">{{ v.note }}</span>
            <span class="faint ver">{{ v.generatorVersion }}</span>
          </li>
        </ul>
      </PaperCard>

      <!-- ── Симуляция режимов ───────────────────────────────────────────────────────────── -->
      <PaperCard class="block">
        <h3 class="serif">Что соберётся из этого контента</h3>
        <p class="faint sub">
          Проверка по каждому тренажёру — тем же условием, которым живая сессия решает, сдавать ли
          карточку. Это не расписание: когда режим откроется ученику, решает лестница.
        </p>
        <ul class="sim">
          <li v-for="m in data.simulation" :key="m.mode" :class="m.status">
            <span class="sim-mode">
              <span class="name">{{ modeLabel(m.mode) }}</span>
              <span class="wire faint">{{ m.mode }}</span>
            </span>
            <Badge :tone="contentStatusTone(m.status)">{{ CONTENT_STATUS_LABEL[m.status] }}</Badge>
            <Badge v-if="m.reason" class="gap">{{ CONTENT_GAP_LABEL[m.reason] ?? m.reason }}</Badge>
            <span class="sim-why muted">{{ m.explanation }}</span>
          </li>
        </ul>
      </PaperCard>

      <!-- ── Нужен станок ────────────────────────────────────────────────────────────────── -->
      <PaperCard class="block topup" :class="{ quiet: !data.needsEnrichment }">
        <h3 class="serif">Нужен станок?</h3>
        <template v-if="data.needsEnrichment">
          <p class="topup-why">
            Да —
            <span v-for="(r, i) in data.needsEnrichmentReasons" :key="r">
              <template v-if="i > 0">, </template>{{ NEEDS_ENRICHMENT_REASON_LABEL[r] ?? r }}
            </span>.
            Цель укомплектованности — {{ data.minDistractors }} пригодных дистрактора; догон одного
            термина стоит примерно ${{ data.costPerTermUsd.toFixed(3) }}.
          </p>
        </template>
        <p v-else-if="data.missingExample" class="topup-why">
          Нет — сначала нужен <strong>пример</strong>. Станок пишет дистракторы и варианты
          <em>против примера</em>; без него платить не за что, это перегенерация примера.
        </p>
        <p v-else class="topup-why">Нет — термин укомплектован.</p>

        <div class="cmd-row">
          <code class="cmd">{{ data.topupCommand }}</code>
          <PaperButton variant="quiet" small @click="copyCommand">
            {{ copied ? 'Скопировано' : 'Скопировать' }}
          </PaperButton>
        </div>
        <p class="faint tiny">
          Панель станок не запускает — это команда для терминала: прогон тратит деньги на модель.
        </p>
        <p v-if="data.topupHint" class="hint">{{ data.topupHint }}</p>
      </PaperCard>

      <PaperCard v-if="data.findings.length" class="block">
        <h3 class="serif">Находки станка</h3>
        <ul class="findings">
          <li v-for="(f, i) in data.findings" :key="i">
            <Badge tone="unsure">{{ f.kind }}</Badge>
            <span v-if="f.field" class="faint">{{ f.field }}</span>
            <span class="detail">{{ f.detail }}</span>
            <span class="faint ver">{{ f.generatorVersion }}</span>
          </li>
        </ul>
      </PaperCard>

      <p v-if="showTermLink" class="term-link">
        <RouterLink :to="{ name: 'term', params: { id: data.termId }, hash: '#health' }">
          Открыть карточку термина →
        </RouterLink>
      </p>
    </template>
  </div>
</template>

<style scoped>
.scope-note {
  margin: 0 0 var(--s12);
  font-size: 12.5px;
  max-width: 78ch;
}
.block {
  margin-bottom: var(--s16);
}
.block h3 {
  margin: 0 0 var(--s12);
  font-size: 17px;
}
.sub {
  margin: -8px 0 var(--s12);
  font-size: 12px;
  max-width: 78ch;
}
.tiny {
  font-size: 11.5px;
}
.note {
  margin: var(--s12) 0 0;
  max-width: 78ch;
}
.kv {
  display: grid;
  grid-template-columns: 190px 1fr;
  gap: var(--s12);
  padding: 8px 0;
  border-bottom: 1px solid var(--divider-faint);
  align-items: baseline;
}
.kv:last-of-type {
  border-bottom: none;
}
.big {
  font-size: 19px;
}
.inline-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}
.lang {
  font-size: 11px;
  margin-right: 6px;
}
.sentence {
  margin: 0;
  font-size: 16px;
}
.tr {
  margin: 2px 0 0;
  font-size: 13.5px;
}
.warn {
  margin: 0;
  font-size: 13px;
  color: var(--destructive-text, #9a4b3f);
}
.versions {
  margin: var(--s12) 0 0;
  font-size: 12px;
}
.distractor {
  margin-top: 10px;
  padding-left: var(--s12);
  border-left: 2px solid var(--hairline);
}
.distractor.dim {
  opacity: 0.55;
}
.d-sentence {
  margin: 0;
  font-size: 14px;
}
.d-sentence mark {
  background: rgba(180, 66, 58, 0.16);
  color: inherit;
  border-radius: 3px;
  padding: 0 2px;
}
.d-meta {
  margin: 2px 0 0;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.correction {
  color: var(--ink);
}
.ver {
  font-size: 11px;
  opacity: 0.7;
}
.sub-section {
  margin-top: var(--s16);
  padding-top: var(--s12);
  border-top: 1px solid var(--divider-faint);
}
.supp-list,
.variants,
.findings {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
}
.supp-list li,
.variants li,
.findings li {
  display: flex;
  align-items: center;
  gap: var(--s12);
  padding: 6px 0;
  border-bottom: 1px solid var(--divider-faint);
  font-size: 13.5px;
}
.supp-list li:last-child,
.variants li:last-child,
.findings li:last-child {
  border-bottom: none;
}
.suppressed .supp-sentence {
  color: var(--secondary);
  flex: 1;
}
.findings .detail {
  flex: 1;
}
.sim {
  list-style: none;
  margin: 0;
  padding: 0;
}
.sim li {
  display: grid;
  grid-template-columns: 190px 130px 170px 1fr;
  gap: var(--s12);
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--divider-faint);
}
.sim li:last-child {
  border-bottom: none;
}
.sim-mode {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.sim-mode .name {
  font-weight: 600;
  font-size: 14px;
}
.sim-mode .wire {
  font-size: 11px;
}
.sim-why {
  font-size: 12.5px;
}
.topup.quiet {
  opacity: 0.85;
}
.topup-why {
  margin: 0 0 var(--s12);
  font-size: 13.5px;
  max-width: 78ch;
}
.cmd-row {
  display: flex;
  align-items: center;
  gap: var(--s12);
  flex-wrap: wrap;
}
.cmd {
  flex: 1;
  min-width: 260px;
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-small);
  padding: 8px 10px;
  font-size: 12.5px;
  overflow-x: auto;
  white-space: nowrap;
}
.hint {
  margin: var(--s12) 0 0;
  font-size: 12.5px;
  color: var(--destructive-text, #9a4b3f);
  max-width: 78ch;
}
.term-link {
  margin: 0;
  font-size: 13.5px;
}
@media (max-width: 900px) {
  .kv,
  .sim li {
    grid-template-columns: 1fr;
  }
}
</style>
