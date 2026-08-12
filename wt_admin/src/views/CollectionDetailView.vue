<script setup lang="ts">
/**
 * A collection: what it costs, what is in it, and the curation of both.
 *
 * Editing a store collection is the normal case here, not the exception — the store is content I
 * curate. Every mutation states its blast radius first (`/impact`), because these decks are shared:
 * removing a term takes it off other people's phones on their next sync.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { count } from '@/utils/format'
import { COLLECTION_SOURCE_LABEL, COLLECTION_TYPE_LABEL, langLabel } from '@/utils/labels'
import type { CollectionImpact, CostByPurpose, TermRow } from '@/api/types'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import PaperButton from '@/components/PaperButton.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import StateBlock from '@/components/StateBlock.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import CostBreakdownCard from '@/components/CostBreakdownCard.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const props = defineProps<{ id: string }>()
const router = useRouter()

const { data, loading, error, run } = useAsync(() => api.getCollection(props.id))
const costs = ref<CostByPurpose | null>(null)
const costsLoading = ref(false)

onMounted(async () => {
  await run()
  costsLoading.value = true
  try {
    costs.value = await api.getCollectionCosts(props.id)
  } catch {
    costs.value = null // the deck still renders without its price tag
  } finally {
    costsLoading.value = false
  }
})

// ── Editing title/description ──
const editing = ref(false)
const draft = ref({ title: '', description: '' })
const saving = ref(false)
const saveError = ref<string | null>(null)

function startEdit() {
  if (!data.value) return
  draft.value = { title: data.value.title, description: data.value.description ?? '' }
  saveError.value = null
  editing.value = true
}

async function save() {
  saving.value = true
  saveError.value = null
  try {
    data.value = await api.updateCollection(props.id, {
      title: draft.value.title,
      description: draft.value.description,
    })
    editing.value = false
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить'
  } finally {
    saving.value = false
  }
}

// ── Membership ──
const addOpen = ref(false)
const termSearch = ref('')
const candidates = ref<TermRow[]>([])
const searching = ref(false)
const mutating = ref(false)
const mutateError = ref<string | null>(null)

async function searchTerms() {
  searching.value = true
  try {
    const res = await api.listTerms({ search: termSearch.value, limit: 10 })
    candidates.value = res.data
  } finally {
    searching.value = false
  }
}

async function addTerm(termId: string) {
  mutating.value = true
  mutateError.value = null
  try {
    data.value = await api.addCollectionTerm(props.id, termId)
    addOpen.value = false
    termSearch.value = ''
    candidates.value = []
  } catch (e) {
    mutateError.value = e instanceof Error ? e.message : 'Не удалось добавить'
  } finally {
    mutating.value = false
  }
}

const removeTarget = ref<{ termId: string; text: string } | null>(null)

async function confirmRemove() {
  if (!removeTarget.value) return
  mutating.value = true
  mutateError.value = null
  try {
    data.value = await api.removeCollectionTerm(props.id, removeTarget.value.termId)
    removeTarget.value = null
  } catch (e) {
    mutateError.value = e instanceof Error ? e.message : 'Не удалось убрать термин'
  } finally {
    mutating.value = false
  }
}

// ── Deletion (for every owner and subscriber) ──
const deleteOpen = ref(false)
const impact = ref<CollectionImpact | null>(null)
const confirmTitle = ref('')
const deleting = ref(false)
const deleteError = ref<string | null>(null)

const titleMatches = computed(() => confirmTitle.value.trim() === (data.value?.title ?? '').trim())

async function askDelete() {
  deleteError.value = null
  confirmTitle.value = ''
  impact.value = null
  deleteOpen.value = true
  try {
    impact.value = await api.getCollectionImpact(props.id)
  } catch {
    impact.value = null
  }
}

async function confirmDelete() {
  deleting.value = true
  deleteError.value = null
  try {
    await api.deleteCollection(props.id, confirmTitle.value.trim())
    router.push({ name: 'collections' })
  } catch (e) {
    deleteError.value = e instanceof Error ? e.message : 'Не удалось удалить'
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div>
    <Breadcrumbs
      :items="[{ label: 'Коллекции', to: { name: 'collections' } }, { label: data?.title ?? id }]"
    />
    <PageHeader
      :title="data?.title ?? 'Коллекция'"
      :back="{ to: { name: 'collections' }, label: 'Коллекции' }"
    >
      <template #actions>
        <template v-if="data && !editing">
          <PaperButton variant="quiet" small @click="startEdit">Редактировать</PaperButton>
          <PaperButton variant="destructive" small @click="askDelete">Удалить</PaperButton>
        </template>
      </template>
    </PageHeader>

    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="data">
      <div class="head-row">
        <ImageThumb v-if="data.imageUrl" :url="data.imageUrl" :size="96" :alt="data.title" />
        <div class="head-body">
          <div class="meta">
            <Badge>{{ COLLECTION_TYPE_LABEL[data.type] }}</Badge>
            <Badge>{{ COLLECTION_SOURCE_LABEL[data.source] }}</Badge>
            <span class="faint">{{ langLabel(data.sourceLang) }} → {{ langLabel(data.targetLang) }}</span>
            <span class="faint">· {{ count(data.itemsCount) }} терминов</span>
          </div>

          <form v-if="editing" class="edit" @submit.prevent="save">
            <label class="f">
              <span class="section-label">Название</span>
              <input v-model="draft.title" type="text" required maxlength="200" />
            </label>
            <label class="f">
              <span class="section-label">Описание</span>
              <textarea v-model="draft.description" rows="3" maxlength="1000" />
            </label>
            <p v-if="saveError" class="err">{{ saveError }}</p>
            <div class="edit-actions">
              <PaperButton small type="submit" :disabled="saving">
                {{ saving ? 'Сохраняем…' : 'Сохранить' }}
              </PaperButton>
              <PaperButton variant="ghost" small type="button" @click="editing = false">Отмена</PaperButton>
            </div>
            <p class="hint">Правка доедет до всех владельцев и подписчиков на следующем синке.</p>
          </form>
          <p v-else-if="data.description" class="desc muted">{{ data.description }}</p>
        </div>
      </div>

      <CostBreakdownCard :costs="costs" :loading="costsLoading" title="Стоимость коллекции" />

      <PaperCard :pad="false" class="wrap">
        <div class="terms-head">
          <span class="section-label">Состав</span>
          <PaperButton variant="quiet" small @click="addOpen = !addOpen">
            {{ addOpen ? 'Закрыть' : 'Добавить термин' }}
          </PaperButton>
        </div>

        <div v-if="addOpen" class="adder">
          <div class="adder-row">
            <input
              v-model="termSearch"
              type="text"
              placeholder="Найти существующий термин"
              @keyup.enter="searchTerms"
            />
            <PaperButton variant="quiet" small :disabled="searching" @click="searchTerms">Искать</PaperButton>
          </div>
          <ul v-if="candidates.length" class="candidates">
            <li v-for="c in candidates" :key="c.id">
              <span class="serif">{{ c.text }}</span>
              <span class="muted">{{ c.translation ?? '—' }}</span>
              <PaperButton variant="quiet" small :disabled="mutating" @click="addTerm(c.id)">Добавить</PaperButton>
            </li>
          </ul>
        </div>

        <p v-if="mutateError" class="err pad">{{ mutateError }}</p>

        <StateBlock v-if="data.terms.length === 0" kind="empty" title="В коллекции нет терминов" />
        <ul v-else class="terms">
          <li v-for="t in data.terms" :key="t.termId" class="term-row">
            <ImageThumb :url="t.imageUrl" :size="44" :alt="t.text" />
            <RouterLink :to="{ name: 'term', params: { id: t.termId } }" class="serif term">
              {{ t.text }}
            </RouterLink>
            <span class="tr muted">{{ t.translation ?? '—' }}</span>
            <PaperButton
              variant="ghost"
              small
              :disabled="mutating"
              @click="removeTarget = { termId: t.termId, text: t.text }"
            >
              Убрать
            </PaperButton>
          </li>
        </ul>
      </PaperCard>
    </template>

    <ConfirmDialog
      :open="removeTarget !== null"
      title="Убрать термин из коллекции?"
      :message="`«${removeTarget?.text}» исчезнет из этой коллекции у всех, кто её изучает. Сам термин останется в словаре и в других коллекциях.`"
      confirm-label="Убрать"
      destructive
      :pending="mutating"
      @confirm="confirmRemove"
      @cancel="removeTarget = null"
    />

    <div v-if="deleteOpen" class="modal-backdrop" @click.self="deleteOpen = false">
      <div class="modal">
        <h3 class="serif">Удалить коллекцию?</h3>
        <p class="modal-text">
          Коллекция исчезнет у <strong>всех</strong> владельцев и подписчиков на следующем синке.
        </p>
        <ul v-if="impact" class="impact">
          <li>Терминов: <strong class="tnum">{{ impact.termsCount }}</strong></li>
          <li>Активных подписок: <strong class="tnum">{{ impact.subscribers }}</strong></li>
          <li>Учеников с прогрессом по этим словам: <strong class="tnum">{{ impact.learnersWithProgress }}</strong></li>
        </ul>
        <p class="modal-text">
          Прогресс по терминам не удаляется — термины остаются в словаре. Введите название коллекции,
          чтобы подтвердить:
        </p>
        <input v-model="confirmTitle" type="text" :placeholder="data?.title" class="confirm-input" />
        <p v-if="deleteError" class="err">{{ deleteError }}</p>
        <div class="modal-actions">
          <PaperButton variant="ghost" small @click="deleteOpen = false">Отмена</PaperButton>
          <PaperButton
            variant="destructive"
            small
            :disabled="!titleMatches || deleting"
            @click="confirmDelete"
          >
            {{ deleting ? 'Удаляем…' : 'Удалить навсегда' }}
          </PaperButton>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.head-row {
  display: flex;
  align-items: flex-start;
  gap: var(--s16);
  margin-bottom: var(--s16);
}
.head-body {
  flex: 1;
}
.meta {
  display: flex;
  align-items: center;
  gap: var(--s12);
  margin-bottom: var(--s8);
  flex-wrap: wrap;
}
.desc {
  margin: 0;
  font-size: 14px;
  max-width: 70ch;
}
.edit {
  display: flex;
  flex-direction: column;
  gap: var(--s12);
  max-width: 60ch;
}
.f {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.f input,
.f textarea,
.confirm-input,
.adder-row input {
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
.hint,
.err {
  font-size: 12px;
  margin: 0;
}
.hint {
  color: var(--secondary);
}
.err {
  color: var(--destructive, #b4423a);
}
.pad {
  padding: var(--s8, 8px) var(--s16);
}
.wrap {
  overflow: hidden;
  margin-top: var(--s16);
}
.terms-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--s12) var(--s16);
  border-bottom: 1px solid var(--hairline);
}
.adder {
  padding: var(--s12) var(--s16);
  border-bottom: 1px solid var(--divider-faint);
  background: var(--faint-ink);
}
.adder-row {
  display: flex;
  gap: var(--s8, 8px);
  align-items: center;
}
.candidates {
  list-style: none;
  margin: var(--s12) 0 0;
  padding: 0;
}
.candidates li {
  display: flex;
  align-items: center;
  gap: var(--s12);
  padding: 6px 0;
}
.candidates li .serif {
  min-width: 140px;
}
.candidates li .muted {
  flex: 1;
  font-size: 13px;
}
.terms {
  list-style: none;
  margin: 0;
  padding: 0;
}
.term-row {
  display: flex;
  align-items: center;
  gap: var(--s16);
  padding: 10px var(--s16);
  border-bottom: 1px solid var(--divider-faint);
}
.term-row:last-child {
  border-bottom: none;
}
.term {
  font-size: 17px;
  flex: 1;
  color: var(--ink);
  text-decoration: none;
}
.term:hover {
  text-decoration: underline;
}
.tr {
  font-size: 14px;
  text-align: right;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: var(--s16);
}
.modal {
  background: var(--paper, #fff);
  border-radius: var(--r-card, 12px);
  padding: var(--s22, 22px);
  max-width: 460px;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--s12);
  box-shadow: var(--shadow-card);
}
.modal h3 {
  margin: 0;
  font-size: 19px;
}
.modal-text {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
}
.impact {
  margin: 0;
  padding-left: 18px;
  font-size: 13.5px;
  line-height: 1.7;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--s8, 8px);
}
</style>
