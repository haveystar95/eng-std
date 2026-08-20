<script setup lang="ts">
/**
 * «Контент» → сводка: чем словарь укомплектован и где догон окупится.
 *
 * Три среза (вся база / системные коллекции / пользовательские) НЕ образуют разбиение и не должны
 * складываться: термин глобальный, он может лежать и в витринной коллекции, и в чьём-то своём
 * списке, а термин без коллекций виден только в «всей базе». Экран говорит это вслух — таблица из
 * трёх строк, чьи числа не сходятся, иначе провоцирует ровно один неверный вывод.
 */
import { onMounted, ref } from 'vue'
import { api } from '@/api'
import { COLLECTION_TYPE_LABEL } from '@/utils/labels'
import { count, money } from '@/utils/format'
import type { ContentHealthCollection, ContentHealthScope, ContentHealthSummary } from '@/api/types'
import Badge from '@/components/Badge.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import PaperCard from '@/components/PaperCard.vue'
import StatCard from '@/components/StatCard.vue'
import StateBlock from '@/components/StateBlock.vue'

const emit = defineEmits<{ open: [collectionId: string] }>()

const data = ref<ContentHealthSummary | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    data.value = await api.getContentHealthSummary()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const SCOPE_LABEL: Record<ContentHealthScope['scope'], string> = {
  all: 'Вся база',
  system: 'Системные коллекции',
  user: 'Пользовательские',
}

const scopeColumns: Column[] = [
  { key: 'scope', label: 'Срез' },
  { key: 'terms', label: 'Терминов', align: 'right', tnum: true },
  { key: 'without_example', label: 'Без примера', align: 'right', tnum: true },
  { key: 'with_distractors', label: 'С дистракторами', align: 'right', tnum: true },
  { key: 'pick_correct_ready', label: 'pick_correct', align: 'right', tnum: true },
  { key: 'with_variants', label: 'С вариантами', align: 'right', tnum: true },
  { key: 'needs_enrichment', label: 'Нужен станок', align: 'right', tnum: true },
  { key: 'estimated', label: 'Догон ≈', align: 'right', tnum: true },
]

const collectionColumns: Column[] = [
  { key: 'title', label: 'Коллекция' },
  { key: 'type', label: 'Тип', width: '110px' },
  { key: 'terms', label: 'Терминов', align: 'right', tnum: true, width: '100px' },
  { key: 'without_example', label: 'Без примера', align: 'right', tnum: true, width: '110px' },
  { key: 'pick_correct_ready', label: 'pick_correct', align: 'right', tnum: true, width: '110px' },
  { key: 'needs_enrichment', label: 'Требуют станка', width: '190px' },
  { key: 'estimated', label: 'Догон ≈', align: 'right', tnum: true, width: '100px' },
]

function scopeRows(s: ContentHealthSummary): ContentHealthScope[] {
  return [s.scopes.all, s.scopes.system, s.scopes.user]
}

/** Worst first: the collections someone would actually go and top up. */
function collectionRows(s: ContentHealthSummary): ContentHealthCollection[] {
  return [...s.collections].sort(
    (a, b) => b.needsEnrichment - a.needsEnrichment || b.withoutExample - a.withoutExample || a.title.localeCompare(b.title),
  )
}
</script>

<template>
  <div>
    <StateBlock v-if="loading && !data" kind="loading" />
    <StateBlock v-else-if="error && !data" kind="error" :message="error" retryable @retry="load" />

    <template v-else-if="data">
      <div class="stats">
        <StatCard label="Терминов всего" :value="count(data.scopes.all.terms)" />
        <StatCard label="Без примера" :value="count(data.scopes.all.withoutExample)" hint="лечится перегенерацией примера, не станком" />
        <StatCard
          label="Требуют станка"
          :value="count(data.scopes.all.needsEnrichment)"
          :hint="`годных дистракторов < ${data.minDistractors} или нет вариантов`"
        />
        <StatCard
          label="Догон ≈"
          :value="money(data.scopes.all.estimatedTopupUsd)"
          :hint="`${money(data.costPerTermUsd)} за термин`"
        />
        <StatCard label="pick_correct собирается" :value="count(data.scopes.all.pickCorrectReady)" hint="эталон + 2 неверных предложения" />
      </div>

      <PaperCard :pad="false" class="block">
        <div class="head">
          <h3 class="serif">Срезы</h3>
          <p class="faint sub">
            Не разбиение и не складывается: термин глобальный — он может лежать и в системной
            коллекции, и в пользовательской, а термин вне коллекций виден только во «всей базе».
          </p>
        </div>
        <DataTable :columns="scopeColumns" :rows="scopeRows(data)" :row-key="(r) => r.scope">
          <template #cell-scope="{ row }">{{ SCOPE_LABEL[row.scope] }}</template>
          <template #cell-terms="{ row }">{{ count(row.terms) }}</template>
          <template #cell-without_example="{ row }">{{ count(row.withoutExample) }}</template>
          <template #cell-with_distractors="{ row }">{{ count(row.withDistractors) }}</template>
          <template #cell-pick_correct_ready="{ row }">{{ count(row.pickCorrectReady) }}</template>
          <template #cell-with_variants="{ row }">{{ count(row.withVariants) }}</template>
          <template #cell-needs_enrichment="{ row }">{{ count(row.needsEnrichment) }}</template>
          <template #cell-estimated="{ row }">{{ money(row.estimatedTopupUsd) }}</template>
        </DataTable>
      </PaperCard>

      <div class="two">
        <PaperCard class="journal">
          <h3 class="serif">Подавления</h3>
          <p class="faint sub">Предложения, которые выкинул человек или аудит. Переживают строку, о которой были.</p>
          <p class="big tnum">{{ count(data.suppressions.total) }}</p>
          <ul class="labels">
            <li v-for="s in data.suppressions.bySource" :key="s.label">
              <Badge tone="unsure">{{ s.label === 'review' ? 'вычитка' : 'аудит' }}</Badge>
              <span class="tnum">{{ count(s.count) }}</span>
            </li>
          </ul>
        </PaperCard>
        <PaperCard class="journal">
          <h3 class="serif">Отказы генерации</h3>
          <p class="faint sub">Что языковой барьер отказался записать: термин так и не был создан.</p>
          <p class="big tnum">{{ count(data.generationRejections.total) }}</p>
          <ul class="labels">
            <li v-for="r in data.generationRejections.byField" :key="r.label">
              <Badge>{{ r.label }}</Badge>
              <span class="tnum">{{ count(r.count) }}</span>
            </li>
            <li v-if="data.generationRejections.byField.length === 0" class="faint">пусто</li>
          </ul>
        </PaperCard>
        <PaperCard class="journal">
          <h3 class="serif">Версии станка</h3>
          <p class="faint sub">Текущая — {{ data.currentGeneratorVersion }}.</p>
          <ul class="labels">
            <li v-for="v in data.scopes.all.enrichmentVersions" :key="v.version ?? 'never'">
              <Badge :tone="v.version === null ? 'unknown' : 'neutral'">
                {{ v.version ?? 'не прогонялся' }}
              </Badge>
              <span class="tnum">{{ count(v.terms) }}</span>
            </li>
          </ul>
        </PaperCard>
      </div>

      <PaperCard :pad="false" class="block">
        <div class="head">
          <h3 class="serif">Коллекции</h3>
          <p class="faint sub">Клик по строке — разбор коллекции по терминам.</p>
        </div>
        <DataTable
          :columns="collectionColumns"
          :rows="collectionRows(data)"
          :row-key="(r) => r.id"
          clickable
          @row-click="(r) => emit('open', r.id)"
        >
          <template #cell-type="{ row }">
            <Badge>{{ COLLECTION_TYPE_LABEL[row.type] ?? row.type }}</Badge>
          </template>
          <template #cell-terms="{ row }">{{ count(row.terms) }}</template>
          <template #cell-without_example="{ row }">{{ count(row.withoutExample) }}</template>
          <template #cell-pick_correct_ready="{ row }">{{ count(row.pickCorrectReady) }}</template>
          <template #cell-needs_enrichment="{ row }">
            <Badge v-if="row.needsEnrichment > 0" tone="unsure">
              {{ row.needsEnrichment }} терминов требуют станка
            </Badge>
            <span v-else class="faint">укомплектована</span>
          </template>
          <template #cell-estimated="{ row }">{{ money(row.estimatedTopupUsd) }}</template>
        </DataTable>
      </PaperCard>
    </template>
  </div>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: var(--s12);
  margin-bottom: var(--s16);
}
.block {
  margin-bottom: var(--s16);
}
.head {
  padding: var(--s16) var(--s16) var(--s8);
}
.head h3 {
  margin: 0;
  font-size: 17px;
}
.sub {
  margin: 4px 0 0;
  font-size: 12px;
  max-width: 82ch;
}
.two {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: var(--s12);
  margin-bottom: var(--s16);
}
.journal h3 {
  margin: 0;
  font-size: 16px;
}
.journal .big {
  margin: var(--s12) 0 var(--s8);
  font-size: 26px;
  font-weight: 800;
}
.labels {
  list-style: none;
  margin: 0;
  padding: 0;
}
.labels li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s8);
  padding: 4px 0;
  font-size: 13px;
}
</style>
