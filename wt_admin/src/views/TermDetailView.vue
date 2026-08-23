<script setup lang="ts">
/**
 * A term, with everything hanging off it, and the editing of it.
 *
 * Terms are GLOBAL — one row per (lang, normalized text, pos), shared by every collection and every
 * learner. So an edit here is felt everywhere at once, which is the feature (fix a bad translation
 * once) and the hazard. The confirm dialog therefore states the blast radius read from `/impact`,
 * not a generic "are you sure".
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { langLabel } from '@/utils/languages'
import type { TermExample, TermImpact } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import PaperButton from '@/components/PaperButton.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import StateBlock from '@/components/StateBlock.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import TermContentPassport from '@/components/TermContentPassport.vue'

const props = defineProps<{ id: string }>()
const router = useRouter()
const route = useRoute()

const { data, loading, error, run } = useAsync(() => api.getTerm(props.id))
onMounted(run)

/**
 * «Здоровье / тренажёры» — read-only section, folded by default and MOUNTED only once opened, so
 * opening the card costs one request as it always did. The passport is a second read (its own
 * endpoint, with the per-trainer simulation) and nobody editing a translation needs it.
 *
 * `#health` in the URL opens it: that anchor is what the Контент section links back to, so the
 * round trip lands on the section rather than at the top of the page.
 */
const healthOpen = ref(route.hash === '#health')
onMounted(async () => {
  if (!healthOpen.value) return
  await nextTick()
  document.getElementById('health')?.scrollIntoView({ block: 'start' })
})

const FINDING_LABEL: Record<string, string> = {
  ambiguity: 'многозначность',
  language: 'язык',
  ua_leakage: 'украинизм',
  misspelled_or_nonword: 'не слово / опечатка',
  variant_conflict: 'конфликт вариантов',
}
const ERROR_TYPE_LABEL: Record<string, string> = {
  article: 'артикль',
  preposition: 'предлог',
  tense: 'время',
  word_order: 'порядок слов',
  false_friend: 'ложный друг',
  modal_to: 'модальный + to',
}

const primaryTranslation = computed(
  () => data.value?.translations.find((t) => t.isPrimary) ?? data.value?.translations[0] ?? null,
)
const pinned = computed(() => data.value?.examples.find((e) => e.isPinned) ?? null)

/**
 * Split a distractor sentence around its `error_span`, so the wrong bit can be marked in place.
 * Falls back to the whole sentence when the span isn't found verbatim — better a plain sentence
 * than a mangled one.
 */
function splitSpan(sentence: string, span: string): [string, string, string] {
  const at = sentence.indexOf(span)
  if (at === -1) return [sentence, '', '']
  return [sentence.slice(0, at), span, sentence.slice(at + span.length)]
}

// ── Editing ──
const editing = ref(false)
const draft = ref({ text: '', translation: '', ipa: '', exampleSentence: '', exampleTranslation: '' })
const saving = ref(false)
const saveError = ref<string | null>(null)
const impact = ref<TermImpact | null>(null)
const confirmSave = ref(false)

async function startEdit() {
  if (!data.value) return
  draft.value = {
    text: data.value.text,
    translation: primaryTranslation.value?.text ?? '',
    ipa: data.value.ipa ?? '',
    exampleSentence: pinned.value?.sentence ?? '',
    exampleTranslation: pinned.value?.translation ?? '',
  }
  saveError.value = null
  editing.value = true
  try {
    impact.value = await api.getTermImpact(props.id)
  } catch {
    impact.value = null
  }
}

const exampleChanged = computed(
  () => pinned.value !== null && draft.value.exampleSentence !== pinned.value.sentence,
)

async function doSave() {
  saving.value = true
  saveError.value = null
  try {
    data.value = await api.updateTerm(props.id, {
      text: draft.value.text,
      translation: draft.value.translation,
      ipa: draft.value.ipa || null,
      ...(pinned.value && exampleChanged.value
        ? {
            exampleId: pinned.value.id,
            exampleSentence: draft.value.exampleSentence,
            exampleTranslation: draft.value.exampleTranslation || null,
          }
        : {}),
    })
    editing.value = false
    confirmSave.value = false
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить'
  } finally {
    saving.value = false
  }
}

// ── Retirement ──
const retireOpen = ref(false)
const retiring = ref(false)
const retireError = ref<string | null>(null)

async function askRetire() {
  retireError.value = null
  retireOpen.value = true
  try {
    impact.value = await api.getTermImpact(props.id)
  } catch {
    impact.value = null
  }
}

async function confirmRetire() {
  retiring.value = true
  retireError.value = null
  try {
    await api.retireTerm(props.id)
    router.push({ name: 'terms' })
  } catch (e) {
    retireError.value = e instanceof Error ? e.message : 'Не удалось удалить'
  } finally {
    retiring.value = false
  }
}

const saveMessage = computed(() => {
  const n = impact.value?.collectionsCount ?? data.value?.collections.length ?? 0
  const users = impact.value?.usersWithProgress ?? data.value?.progressCount ?? 0
  const base = `Термин используется в ${n} коллекц${plural(n, 'ии', 'иях', 'иях')}, у ${users} ${plural(users, 'ученика', 'учеников', 'учеников')} есть прогресс. Правка отразится везде.`
  return exampleChanged.value
    ? base +
        ' Пример изменён: дистракторы «найди ошибку» для него будут удалены, а термин уйдёт на пере-обогащение.'
    : base
})

function plural(n: number, one: string, few: string, many: string): string {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few
  return many
}

function exampleKey(e: TermExample): string {
  return e.id
}
</script>

<template>
  <div>
    <Breadcrumbs :items="[{ label: 'Термины', to: { name: 'terms' } }, { label: data?.text ?? id }]" />
    <PageHeader :title="data?.text ?? 'Термин'" :back="{ to: { name: 'terms' }, label: 'Термины' }">
      <template #actions>
        <template v-if="data && !editing">
          <PaperButton variant="quiet" small @click="startEdit">Редактировать</PaperButton>
          <PaperButton variant="destructive" small @click="askRetire">Удалить</PaperButton>
        </template>
      </template>
    </PageHeader>

    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="data">
      <PaperCard class="head">
        <ImageThumb v-if="data.imageUrl" :url="data.imageUrl" :size="96" :alt="data.text" />
        <div class="head-body">
          <form v-if="editing" class="edit" @submit.prevent="confirmSave = true">
            <div class="edit-grid">
              <label class="f">
                <span class="section-label">Термин</span>
                <input v-model="draft.text" type="text" required maxlength="200" />
              </label>
              <label class="f">
                <span class="section-label">Перевод</span>
                <input v-model="draft.translation" type="text" maxlength="200" />
              </label>
              <label class="f">
                <span class="section-label">Транскрипция</span>
                <input v-model="draft.ipa" type="text" maxlength="200" placeholder="/ˈledʒər/" />
              </label>
            </div>
            <template v-if="pinned">
              <label class="f">
                <span class="section-label">Закреплённый пример</span>
                <input v-model="draft.exampleSentence" type="text" maxlength="500" />
              </label>
              <label class="f">
                <span class="section-label">Перевод примера</span>
                <input v-model="draft.exampleTranslation" type="text" maxlength="500" />
              </label>
            </template>
            <p v-if="saveError" class="err">{{ saveError }}</p>
            <div class="edit-actions">
              <PaperButton small type="submit" :disabled="saving">Сохранить</PaperButton>
              <PaperButton variant="ghost" small type="button" @click="editing = false">Отмена</PaperButton>
            </div>
          </form>

          <template v-else>
            <div class="meta">
              <Badge>{{ data.type }}</Badge>
              <Badge v-if="data.cefr">{{ data.cefr }}</Badge>
              <span class="faint">{{ langLabel(data.lang) }}</span>
              <span v-if="data.ipa" class="ipa">{{ data.ipa }}</span>
              <span v-if="data.pos" class="faint">· {{ data.pos }}</span>
            </div>
            <p class="translation serif">{{ primaryTranslation?.text ?? '—' }}</p>
            <ul v-if="data.translations.length > 1" class="alt">
              <li v-for="t in data.translations.slice(1)" :key="t.text" class="muted">{{ t.text }}</li>
            </ul>
            <p class="footprint faint">
              В {{ data.collections.length }} коллекциях · прогресс у {{ data.progressCount }} ·
              станок: {{ data.enrichmentVersion ?? 'не прогонялся' }}
            </p>
          </template>
        </div>
      </PaperCard>

      <PaperCard v-if="data.findings.length" class="block findings">
        <h3 class="serif">Находки станка</h3>
        <ul>
          <li v-for="(f, i) in data.findings" :key="i">
            <Badge tone="unsure">{{ FINDING_LABEL[f.kind] ?? f.kind }}</Badge>
            <span v-if="f.field" class="faint">{{ f.field }}</span>
            <span class="detail">{{ f.detail }}</span>
            <span class="faint ver">{{ f.generatorVersion }}</span>
          </li>
        </ul>
      </PaperCard>

      <PaperCard class="block">
        <h3 class="serif">Примеры</h3>
        <p v-if="data.examples.length === 0" class="faint">Примеров нет.</p>
        <div v-for="e in data.examples" :key="exampleKey(e)" class="example">
          <div class="ex-head">
            <Badge v-if="e.isPinned" tone="known">закреплён</Badge>
            <span v-else class="faint">запасной</span>
          </div>
          <p class="ex-sentence serif">{{ e.sentence }}</p>
          <p v-if="e.translation" class="ex-tr muted">{{ e.translation }}</p>

          <div v-if="e.distractors.length" class="distractors">
            <div class="section-label">Дистракторы «найди ошибку»</div>
            <div v-for="d in e.distractors" :key="d.id" class="distractor">
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
          </div>
        </div>
      </PaperCard>

      <PaperCard v-if="data.acceptedVariants.length" class="block">
        <h3 class="serif">Принимаемые варианты</h3>
        <p class="faint sub">Эти написания сервер (и клиент) засчитывают как верный ответ.</p>
        <ul class="variants">
          <li v-for="v in data.acceptedVariants" :key="v.text">
            <span class="serif">{{ v.text }}</span>
            <span v-if="v.note" class="muted">{{ v.note }}</span>
            <span class="faint ver">{{ v.generatorVersion }}</span>
          </li>
        </ul>
      </PaperCard>

      <section id="health" class="block health">
        <PaperCard>
          <button class="health-head" type="button" :aria-expanded="healthOpen" @click="healthOpen = !healthOpen">
            <span class="health-title">
              <span class="serif h">Здоровье / тренажёры</span>
              <span class="faint sub">
                Что позволяет собрать КОНТЕНТ термина — примеры, дистракторы, варианты. Когда
                тренажёр откроется ученику, решает лестница, это другой экран.
              </span>
            </span>
            <span class="chev" aria-hidden="true">{{ healthOpen ? '−' : '+' }}</span>
          </button>
        </PaperCard>
        <div v-if="healthOpen" class="health-body">
          <TermContentPassport :term-id="id" :show-term-link="false" />
          <p class="health-link">
            <RouterLink :to="{ name: 'content', query: { term: id } }">Открыть в разделе «Контент» →</RouterLink>
          </p>
        </div>
      </section>

      <PaperCard class="block">
        <h3 class="serif">Коллекции</h3>
        <p v-if="data.collections.length === 0" class="faint">Ни в одной коллекции.</p>
        <ul v-else class="collections">
          <li v-for="c in data.collections" :key="c.id">
            <RouterLink :to="{ name: 'collection', params: { id: c.id } }">{{ c.title }}</RouterLink>
            <Badge>{{ c.type }}</Badge>
          </li>
        </ul>
      </PaperCard>
    </template>

    <ConfirmDialog
      :open="confirmSave"
      title="Сохранить правку термина?"
      :message="saveMessage"
      confirm-label="Сохранить"
      :pending="saving"
      @confirm="doSave"
      @cancel="confirmSave = false"
    />

    <ConfirmDialog
      :open="retireOpen"
      title="Удалить термин?"
      :message="`Термин входит в ${impact?.collectionsCount ?? 0} коллекц${plural(impact?.collectionsCount ?? 0, 'ию', 'ии', 'ий')}, прогресс есть у ${impact?.usersWithProgress ?? 0}. Строки прогресса будут удалены; журнал ревью (${impact?.reviewsCount ?? 0} ответов) не трогается — это история. Термин исчезнет со всех устройств после синка.`"
      confirm-label="Удалить навсегда"
      destructive
      :pending="retiring"
      @confirm="confirmRetire"
      @cancel="retireOpen = false"
    />
    <p v-if="retireError" class="err">{{ retireError }}</p>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  gap: var(--s16);
  align-items: flex-start;
}
.head-body {
  flex: 1;
}
.meta {
  display: flex;
  align-items: center;
  gap: var(--s12);
  flex-wrap: wrap;
  margin-bottom: var(--s8);
}
.ipa {
  font-size: 13px;
  color: var(--secondary);
}
.translation {
  margin: 0;
  font-size: 22px;
}
.alt {
  list-style: none;
  margin: 4px 0 0;
  padding: 0;
  display: flex;
  gap: var(--s12);
  flex-wrap: wrap;
  font-size: 13px;
}
.footprint {
  margin: var(--s12) 0 0;
  font-size: 12px;
}
.block {
  margin-top: var(--s16);
}
.block h3 {
  margin: 0 0 var(--s12);
  font-size: 17px;
}
.sub {
  margin: -8px 0 var(--s12);
  font-size: 12px;
}
.example {
  padding: var(--s12) 0;
  border-bottom: 1px solid var(--divider-faint);
}
.example:last-child {
  border-bottom: none;
}
.ex-head {
  margin-bottom: 6px;
}
.ex-sentence {
  margin: 0;
  font-size: 16px;
}
.ex-tr {
  margin: 2px 0 0;
  font-size: 13.5px;
}
.distractors {
  margin-top: var(--s12);
  padding-left: var(--s12);
  border-left: 2px solid var(--hairline);
}
.distractor {
  margin-top: 8px;
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
}
.correction {
  color: var(--ink);
}
.ver {
  font-size: 11px;
  opacity: 0.7;
}
.variants,
.collections,
.findings ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
.variants li,
.collections li,
.findings li {
  display: flex;
  align-items: center;
  gap: var(--s12);
  padding: 6px 0;
  border-bottom: 1px solid var(--divider-faint);
  font-size: 13.5px;
}
.variants li:last-child,
.collections li:last-child,
.findings li:last-child {
  border-bottom: none;
}
.findings .detail {
  flex: 1;
}
.edit,
.edit-grid {
  display: flex;
  flex-direction: column;
  gap: var(--s12);
}
.edit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 8px 12px;
  font-size: 14px;
  font-family: inherit;
  width: 100%;
}
.edit-actions {
  display: flex;
  gap: var(--s8, 8px);
}
.health-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--s16);
  width: 100%;
  background: transparent;
  border: none;
  padding: 0;
  text-align: left;
  cursor: pointer;
  color: inherit;
}
.health-title {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.health-title .h {
  font-size: 17px;
}
.health-title .sub {
  font-size: 12px;
  max-width: 78ch;
}
.chev {
  font-size: 20px;
  line-height: 1;
  color: var(--secondary);
}
.health-body {
  margin-top: var(--s12);
}
.health-link {
  margin: 0;
  font-size: 13.5px;
}
.err {
  color: var(--destructive, #b4423a);
  font-size: 12.5px;
  margin: 0;
}
</style>
