<script setup lang="ts">
/**
 * «Песочница» — попробовать промпт на модели и тут же прогнать результат через НАСТОЯЩИЙ валидатор
 * дистракторов.
 *
 * Два правила, на которых экран держится:
 *
 *  - **В базу не пишется ничего.** Ни дистракторы, ни подавления, ни отметки версий. Это сказано на
 *    экране, а не только в коде: человек, который видит «выжило 2 из 5», должен быть уверен, что эти
 *    двое никуда не поехали.
 *  - **Вердикты настоящие.** Их выносит тот же валидатор, что гоняет станок, на настоящем эталоне.
 *    Песочница, которая совпадала бы с продом «примерно», хуже, чем никакой: ей бы поверили.
 *
 * Промпт живёт в localStorage браузера — перезагрузка страницы посреди эксперимента не должна
 * стоить пятнадцати минут набора.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import { findDistractorItems, itemsHint } from '@/utils/distractorItems'
import { money } from '@/utils/format'
import type {
  PlaygroundProvider,
  PlaygroundResult,
  PlaygroundValidation,
  TermRow,
} from '@/api/types'
import Badge from '@/components/Badge.vue'
import JsonTree from '@/components/JsonTree.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import SearchInput from '@/components/SearchInput.vue'
import StateBlock from '@/components/StateBlock.vue'

// ── The request panel ───────────────────────────────────────────────────────────────────────────

const PROMPT_KEY = 'wt_admin.playground.prompt'
const PICK_KEY = 'wt_admin.playground.pick'

const providers = ref<PlaygroundProvider[]>([])
const providersError = ref<string | null>(null)
const provider = ref('')
const model = ref('')
const prompt = ref('')
const temperature = ref<string>('')

const sending = ref(false)
const result = ref<PlaygroundResult | null>(null)

const current = computed(() => providers.value.find((p) => p.provider === provider.value) ?? null)
const models = computed(() => current.value?.models ?? [])
const canSend = computed(() => !!current.value?.available && model.value !== '' && prompt.value.trim() !== '')

onMounted(async () => {
  try {
    providers.value = await api.getPlaygroundProviders()
  } catch (e) {
    providersError.value = e instanceof Error ? e.message : 'Не удалось получить список провайдеров'
  }

  prompt.value = localStorage.getItem(PROMPT_KEY) ?? ''
  const saved = localStorage.getItem(PICK_KEY)?.split('|') ?? []
  // A saved pick is only honoured if it still exists AND is still usable — a key can be removed
  // between two sessions, and silently keeping a dead provider selected reads as a broken screen.
  const savedProvider = providers.value.find((p) => p.provider === saved[0] && p.available)
  const first = savedProvider ?? providers.value.find((p) => p.available) ?? providers.value[0] ?? null
  provider.value = first?.provider ?? ''
  model.value = (savedProvider && saved[1] && first?.models.includes(saved[1]) ? saved[1] : first?.models[0]) ?? ''
})

watch(prompt, (v) => localStorage.setItem(PROMPT_KEY, v))
watch([provider, model], ([p, m]) => {
  if (p !== '') localStorage.setItem(PICK_KEY, `${p}|${m}`)
})
// Switching provider re-points the model at that provider's own list, never at a name it does not
// offer — the backend would refuse it by name, which is a worse way to find out.
watch(provider, () => {
  if (!models.value.includes(model.value)) model.value = models.value[0] ?? ''
})

async function send(): Promise<void> {
  sending.value = true
  validation.value = null
  try {
    const t = temperature.value.trim()
    result.value = await api.playgroundGenerate({
      provider: provider.value,
      model: model.value,
      prompt: prompt.value,
      temperature: t === '' ? null : Number(t),
    })
  } catch (e) {
    result.value = {
      provider: provider.value,
      model: model.value,
      rawText: '',
      parsedJson: null,
      parseError: null,
      usage: { tokensIn: null, tokensOut: null, costUsd: null },
      latencyMs: 0,
      error: e instanceof Error ? e.message : 'Ошибка запроса',
    }
  } finally {
    sending.value = false
  }
}

const copied = ref(false)
async function copyAnswer(): Promise<void> {
  const text = result.value?.rawText ?? ''
  if (text === '') return
  try {
    await navigator.clipboard?.writeText(text)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    // The text is on screen and selectable — a refused clipboard is not worth an error box.
  }
}

// ── The validation block ────────────────────────────────────────────────────────────────────────

const found = computed(() => findDistractorItems(result.value?.parsedJson ?? null))
const hint = computed(() => itemsHint(found.value))

const manualMode = ref(false)
const manualTerm = ref('')
const manualExample = ref('')

const termQuery = ref('')
const termHits = ref<TermRow[]>([])
const pickedTerm = ref<TermRow | null>(null)
const searching = ref(false)

async function searchTerms(q: string): Promise<void> {
  if (q === '') {
    termHits.value = []
    return
  }
  searching.value = true
  try {
    termHits.value = (await api.listTerms({ search: q, perPage: 8 })).data
  } catch {
    termHits.value = []
  } finally {
    searching.value = false
  }
}

function pickTerm(term: TermRow): void {
  pickedTerm.value = term
  termHits.value = []
  termQuery.value = ''
}

const validating = ref(false)
const validation = ref<PlaygroundValidation | null>(null)
const validationError = ref<string | null>(null)

const referenceReady = computed(() =>
  manualMode.value ? manualTerm.value.trim() !== '' && manualExample.value.trim() !== '' : pickedTerm.value !== null,
)
const canValidate = computed(() => found.value.items.length > 0 && referenceReady.value && !validating.value)

async function validate(): Promise<void> {
  validating.value = true
  validationError.value = null
  try {
    validation.value = await api.playgroundValidate({
      items: found.value.items,
      termId: manualMode.value ? null : (pickedTerm.value?.id ?? null),
      manual: manualMode.value
        ? { term_text: manualTerm.value.trim(), example_text: manualExample.value.trim() }
        : undefined,
    })
  } catch (e) {
    validationError.value = e instanceof Error ? e.message : 'Не удалось провалидировать'
  } finally {
    validating.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Песочница"
      subtitle="Промпт уходит модели как есть, без наших шаблонов. Результат можно прогнать через настоящий валидатор дистракторов."
    />

    <p class="nowrite">
      <Badge tone="unsure">песочница</Badge>
      В базу не пишется ничего — ни дистракторы, ни подавления, ни отметки версий. Вызов модели
      стоит денег и попадает в общий журнал расходов.
    </p>

    <!-- ── Запрос ──────────────────────────────────────────────────────────────────────────── -->
    <PaperCard class="block">
      <h3 class="serif">Запрос</h3>
      <StateBlock v-if="providersError" kind="error" :message="providersError" />

      <div class="controls">
        <label class="f">
          <span class="section-label">Провайдер</span>
          <select v-model="provider">
            <option
              v-for="p in providers"
              :key="p.provider"
              :value="p.provider"
              :disabled="!p.available"
              :title="p.available ? undefined : p.reason"
            >
              {{ p.label }}{{ p.available ? '' : ` — ${p.reason}` }}
            </option>
          </select>
        </label>
        <label class="f">
          <span class="section-label">Модель</span>
          <select v-model="model" :disabled="models.length === 0">
            <option v-for="m in models" :key="m" :value="m">{{ m }}</option>
          </select>
        </label>
        <label class="f narrow">
          <span class="section-label">Temperature</span>
          <input v-model="temperature" type="number" min="0" max="2" step="0.1" placeholder="по умолчанию" />
        </label>
      </div>
      <p v-if="current && !current.available" class="warn">
        {{ current.label }}: {{ current.reason }} — выбрать нельзя, пока ключ не задан.
      </p>

      <label class="f">
        <span class="section-label">Промпт</span>
        <textarea
          v-model="prompt"
          class="prompt"
          rows="12"
          spellcheck="false"
          placeholder="Промпт уходит как есть — ни системного сообщения, ни схемы."
        />
      </label>
      <p class="faint tiny">Промпт сохраняется в этом браузере, перезагрузка его не потеряет.</p>

      <div class="actions">
        <PaperButton :disabled="!canSend || sending" @click="send">
          {{ sending ? 'Отправляем…' : 'Отправить' }}
        </PaperButton>
      </div>
    </PaperCard>

    <!-- ── Ответ ───────────────────────────────────────────────────────────────────────────── -->
    <PaperCard v-if="result" class="block">
      <div class="answer-head">
        <h3 class="serif">Ответ</h3>
        <PaperButton v-if="result.rawText" variant="quiet" small @click="copyAnswer">
          {{ copied ? 'Скопировано' : 'Скопировать' }}
        </PaperButton>
      </div>

      <p v-if="result.error" class="err-box">{{ result.error }}</p>

      <template v-else>
        <div class="usage">
          <span class="chip"><span class="faint">модель</span> {{ result.model }}</span>
          <span class="chip"><span class="faint">токены</span> {{ result.usage.tokensIn ?? '—' }} → {{ result.usage.tokensOut ?? '—' }}</span>
          <span class="chip">
            <span class="faint">стоимость</span>
            {{ result.usage.costUsd === null ? 'не тарифицируется' : money(Number(result.usage.costUsd)) }}
          </span>
          <span class="chip"><span class="faint">задержка</span> {{ result.latencyMs }} мс</span>
        </div>

        <div v-if="result.parsedJson !== null && result.parseError === null" class="tree">
          <JsonTree :value="result.parsedJson" />
        </div>
        <template v-else>
          <p class="warn">Не JSON: {{ result.parseError }}</p>
          <pre class="raw">{{ result.rawText }}</pre>
        </template>
      </template>
    </PaperCard>

    <!-- ── Валидация ───────────────────────────────────────────────────────────────────────── -->
    <PaperCard v-if="result && !result.error" class="block">
      <h3 class="serif">Прогнать через валидатор</h3>
      <p class="faint sub">
        Те же проверки, что у станка, на настоящем эталоне. Ничего не сохраняется.
      </p>

      <div class="ref-switch">
        <label><input v-model="manualMode" type="radio" :value="false" /> Термин из базы</label>
        <label><input v-model="manualMode" type="radio" :value="true" /> Ручной эталон</label>
      </div>

      <div v-if="!manualMode" class="term-pick">
        <SearchInput v-model="termQuery" placeholder="Поиск термина…" @search="searchTerms" />
        <span v-if="searching" class="faint tiny">ищем…</span>
        <ul v-if="termHits.length" class="hits">
          <li v-for="t in termHits" :key="t.id">
            <button type="button" @click="pickTerm(t)">
              <span class="serif">{{ t.text }}</span>
              <span class="muted">{{ t.translation ?? '—' }}</span>
            </button>
          </li>
        </ul>
        <p v-if="pickedTerm" class="picked">
          Эталон: <span class="serif">{{ pickedTerm.text }}</span>
          <button type="button" class="clear" @click="pickedTerm = null">сбросить</button>
        </p>
        <p v-else class="faint tiny">Выберите термин — из него возьмутся закреплённый пример, формы ответа и дедуп с базой.</p>
      </div>

      <div v-else class="manual">
        <label class="f">
          <span class="section-label">Термин</span>
          <input v-model="manualTerm" type="text" maxlength="200" placeholder="grain-free" />
        </label>
        <label class="f">
          <span class="section-label">Пример</span>
          <input v-model="manualExample" type="text" maxlength="1000" placeholder="This food is completely grain-free." />
        </label>
        <p class="faint tiny">
          Дедуп с базой — только по тексту термина; подавления применяются, если такой термин нашёлся.
        </p>
      </div>

      <p v-if="found.items.length" class="faint tiny">
        Нашли {{ found.items.length }} строк{{ found.items.length === 1 ? 'у' : '' }} в
        <code>{{ found.path === '' ? 'корне ответа' : found.path }}</code>.
      </p>
      <p v-else class="warn">{{ hint }}</p>

      <div class="actions">
        <PaperButton :disabled="!canValidate" @click="validate">
          {{ validating ? 'Проверяем…' : 'Валидировать' }}
        </PaperButton>
        <span v-if="found.items.length && !referenceReady" class="faint tiny">
          сначала выберите эталон
        </span>
      </div>
      <p v-if="validationError" class="err-box">{{ validationError }}</p>
    </PaperCard>

    <!-- ── Результат валидации ─────────────────────────────────────────────────────────────── -->
    <PaperCard v-if="validation" class="block">
      <div class="answer-head">
        <h3 class="serif">Результат</h3>
        <span class="survived">
          Выжило <strong>{{ validation.kept }}</strong> из {{ validation.total }}
        </span>
      </div>
      <p class="faint sub">
        Эталон: <span class="serif">{{ validation.termText || '—' }}</span>
        <template v-if="validation.exampleSentence"> · «{{ validation.exampleSentence }}»</template>
        <template v-if="validation.source === 'term'">
          · в дедупе {{ validation.existingCount }} предложений, из них подавленных {{ validation.suppressedCount }}
        </template>
        <template v-else-if="validation.matchedTermId"> · дедуп взят у найденного термина</template>
      </p>

      <ul class="verdicts">
        <li v-for="row in validation.items" :key="row.index" :class="row.verdict.toLowerCase()">
          <div class="v-head">
            <Badge :tone="row.verdict === 'KEEP' ? 'known' : 'unknown'" solid>{{ row.verdict }}</Badge>
            <span class="v-sentence">{{ row.sentence }}</span>
          </div>
          <p class="v-reason muted">{{ row.reason }}</p>
          <p class="v-meta faint tiny">
            <code>{{ row.gate }}</code>
            · span «{{ row.errorSpan }}» → «{{ row.correction }}»
            · {{ row.errorType }}<template v-if="row.errorTypeDefaulted"> (подставлен — в ответе типа не было)</template>
          </p>
        </li>
      </ul>

      <p class="nowrite bottom">
        <Badge tone="unsure">песочница</Badge>
        Ничего из этого не сохранено: чтобы строки попали в базу, их пишет станок, а не этот экран.
      </p>
    </PaperCard>
  </div>
</template>

<style scoped>
.nowrite {
  display: flex;
  align-items: center;
  gap: var(--s8);
  flex-wrap: wrap;
  margin: 0 0 var(--s16);
  font-size: 12.5px;
  color: var(--secondary);
  max-width: 90ch;
}
.nowrite.bottom {
  margin: var(--s16) 0 0;
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
  max-width: 90ch;
}
.tiny {
  font-size: 11.5px;
}
.controls {
  display: flex;
  gap: var(--s16);
  flex-wrap: wrap;
  margin-bottom: var(--s12);
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f select,
.f input[type='text'],
.f input[type='number'] {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
  color: var(--ink);
  min-width: 220px;
}
.f.narrow input {
  min-width: 140px;
}
.prompt {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 10px 12px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12.5px;
  line-height: 1.55;
  width: 100%;
  resize: vertical;
}
.actions {
  display: flex;
  align-items: center;
  gap: var(--s12);
  margin-top: var(--s12);
  flex-wrap: wrap;
}
.answer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s12);
  margin-bottom: var(--s12);
}
.answer-head h3 {
  margin: 0;
}
.survived {
  font-size: 14px;
}
.usage {
  display: flex;
  gap: var(--s8);
  flex-wrap: wrap;
  margin-bottom: var(--s12);
}
.chip {
  display: inline-flex;
  gap: 6px;
  align-items: baseline;
  background: var(--faint-ink);
  border-radius: var(--r-pill);
  padding: 4px 10px;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
.tree {
  max-height: 520px;
  overflow: auto;
  padding: var(--s8) 0;
}
.raw {
  margin: var(--s8) 0 0;
  padding: 10px 12px;
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
  white-space: pre-wrap;
  max-height: 420px;
  overflow: auto;
}
.warn {
  margin: var(--s8) 0 0;
  font-size: 12.5px;
  color: var(--destructive-text, #9a4b3f);
}
.err-box {
  margin: 0;
  padding: 10px 12px;
  border: 1px solid var(--destructive);
  border-radius: var(--r-field);
  color: var(--destructive-text, #9a4b3f);
  font-size: 12.5px;
  white-space: pre-wrap;
}
.ref-switch {
  display: flex;
  gap: var(--s16);
  margin-bottom: var(--s12);
  font-size: 13.5px;
}
.term-pick,
.manual {
  display: flex;
  flex-direction: column;
  gap: var(--s8);
}
.hits {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  max-width: 520px;
  overflow: hidden;
}
.hits li + li {
  border-top: 1px solid var(--divider-faint);
}
.hits button {
  display: flex;
  gap: var(--s12);
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 13.5px;
  color: inherit;
}
.hits button:hover {
  background: var(--faint-ink);
}
.picked {
  margin: 0;
  font-size: 13.5px;
}
.clear {
  margin-left: var(--s8);
  border: none;
  background: transparent;
  color: var(--secondary);
  font-size: 12px;
  cursor: pointer;
  text-decoration: underline;
}
.verdicts {
  list-style: none;
  margin: 0;
  padding: 0;
}
.verdicts li {
  padding: 10px 0 10px 12px;
  border-left: 3px solid var(--hairline);
  border-bottom: 1px solid var(--divider-faint);
}
.verdicts li:last-child {
  border-bottom: none;
}
.verdicts li.keep {
  border-left-color: var(--verdict-known);
}
.verdicts li.reject {
  border-left-color: var(--destructive);
}
.v-head {
  display: flex;
  align-items: baseline;
  gap: var(--s8);
  flex-wrap: wrap;
}
.v-sentence {
  font-size: 14px;
}
.v-reason {
  margin: 4px 0 0;
  font-size: 13px;
}
.v-meta {
  margin: 2px 0 0;
}
</style>
