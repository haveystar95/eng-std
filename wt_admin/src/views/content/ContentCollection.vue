<script setup lang="ts">
/**
 * «Контент» → одна коллекция: её термины, самые неукомплектованные сверху (порядок приходит с
 * сервера — сначала «нет примера», затем меньше всего пригодных дистракторов).
 *
 * «Пригодных дистракторов» — не число строк в таблице: карточка берёт по одному дистрактору на
 * фрагмент ошибки, поэтому три строки с одним и тем же фрагментом дают одну опцию. Колонка
 * показывает оба числа, когда они расходятся, иначе догон выглядит уже сделанным.
 *
 * И «пригодных», а не «годных», намеренно. Это число говорит ровно одно: карточка СОБЕРЁТСЯ —
 * span находится в своём предложении, correction чинит его до эталона, строка не дубль и не наш же
 * верный ответ. Все проверки за ним — сравнение строк. Грамматичен ли «неверный» вариант, код не
 * знает и знать не может: «The post office was next to the museum» пройдёт их все и накажет ученика
 * за верный выбор. Колонка, названная «годными», обещала качество, которого никто не проверял.
 */
import { onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import { COLLECTION_TYPE_LABEL, NEEDS_ENRICHMENT_REASON_LABEL } from '@/utils/labels'
import { count, money } from '@/utils/format'
import type { CollectionContentHealth } from '@/api/types'
import Badge from '@/components/Badge.vue'
import DataTable, { type Column } from '@/components/DataTable.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import StatCard from '@/components/StatCard.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{ collectionId: string }>()
const emit = defineEmits<{ open: [termId: string]; title: [title: string] }>()

const data = ref<CollectionContentHealth | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const copied = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    data.value = await api.getCollectionContentHealth(props.collectionId)
    emit('title', data.value.title)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => props.collectionId, load)

async function copyCommand(): Promise<void> {
  const text = data.value?.topupCommand
  if (!text) return
  try {
    await navigator.clipboard?.writeText(text)
    copied.value = true
    setTimeout(() => (copied.value = false), 1600)
  } catch {
    // The command is on screen and selectable — a refused clipboard is not worth an error box.
  }
}

const columns: Column[] = [
  { key: 'text', label: 'Термин' },
  { key: 'translation', label: 'Перевод', truncate: '260px' },
  { key: 'example', label: 'Пример', width: '90px' },
  { key: 'distractors', label: 'Пригодных дистр.', align: 'right', width: '150px' },
  { key: 'pick_correct', label: 'pick_correct', width: '120px' },
  { key: 'variants', label: 'Вариантов', align: 'right', tnum: true, width: '100px' },
  { key: 'version', label: 'Версия станка', width: '130px' },
  { key: 'needs', label: 'Что догнать', width: '230px' },
]
</script>

<template>
  <div>
    <StateBlock v-if="loading && !data" kind="loading" />
    <StateBlock v-else-if="error && !data" kind="error" :message="error" retryable @retry="load" />

    <template v-else-if="data">
      <div class="stats">
        <StatCard label="Терминов" :value="count(data.terms.length)" />
        <StatCard label="Без примера" :value="count(data.withoutExample)" hint="перегенерация примера, не станок" />
        <StatCard
          label="Без запаса"
          :value="count(data.needsEnrichment)"
          :hint="`меньше ${data.minDistractors} пригодных — карточка держится на грани`"
        />
        <StatCard label="pick_correct собирается" :value="count(data.pickCorrectReady)" />
        <StatCard label="Догон ≈" :value="money(data.estimatedTopupUsd)" :hint="`${money(data.costPerTermUsd)} за термин`" />
      </div>

      <PaperCard class="cmd-card">
        <div class="cmd-row">
          <div>
            <div class="section-label">Догон всей коллекции</div>
            <code class="cmd">{{ data.topupCommand }}</code>
          </div>
          <PaperButton variant="quiet" small @click="copyCommand">
            {{ copied ? 'Скопировано' : 'Скопировать' }}
          </PaperButton>
        </div>
        <p class="faint tiny">
          Панель станок не запускает — прогон тратит деньги на модель, это команда для терминала.
          <Badge class="type">{{ COLLECTION_TYPE_LABEL[data.type] ?? data.type }}</Badge>
        </p>
      </PaperCard>

      <PaperCard :pad="false">
        <DataTable
          :columns="columns"
          :rows="data.terms"
          :row-key="(r) => r.termId"
          clickable
          sticky-header
          @row-click="(r) => emit('open', r.termId)"
        >
          <template #cell-text="{ row }">
            <span class="serif term">{{ row.text }}</span>
          </template>
          <template #cell-translation="{ row }">
            <span class="muted">{{ row.translation ?? '—' }}</span>
          </template>
          <template #cell-example="{ row }">
            <Badge v-if="row.hasExample" tone="known">есть</Badge>
            <Badge v-else tone="unknown">нет</Badge>
          </template>
          <template #cell-distractors="{ row }">
            <span class="tnum">{{ row.usableDistractors }}</span>
            <!-- Rows that collide on one error span never reach a card; showing only the raw count
                 would read as «уже укомплектован». -->
            <span v-if="row.rawDistractors !== row.usableDistractors" class="faint tnum raw">
              из {{ row.rawDistractors }}
            </span>
          </template>
          <template #cell-pick_correct="{ row }">
            <Badge :tone="row.pickCorrectReady ? 'known' : 'unknown'">
              {{ row.pickCorrectReady ? 'собирается' : 'нет' }}
            </Badge>
          </template>
          <template #cell-variants="{ row }">{{ row.variants }}</template>
          <template #cell-version="{ row }">
            <span class="faint">{{ row.enrichmentVersion ?? 'не прогонялся' }}</span>
          </template>
          <template #cell-needs="{ row }">
            <template v-if="row.needsEnrichment">
              <Badge
                v-for="r in row.needsEnrichmentReasons"
                :key="r"
                tone="unsure"
                class="reason"
              >
                {{ NEEDS_ENRICHMENT_REASON_LABEL[r] ?? r }}
              </Badge>
            </template>
            <span v-else-if="row.missingExample" class="faint">нужен пример</span>
            <span v-else class="faint">—</span>
          </template>
        </DataTable>
      </PaperCard>
    </template>
  </div>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--s12);
  margin-bottom: var(--s16);
}
.cmd-card {
  margin-bottom: var(--s16);
}
.cmd-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--s12);
  flex-wrap: wrap;
}
.cmd {
  display: block;
  margin-top: 4px;
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-small);
  padding: 8px 10px;
  font-size: 12.5px;
  overflow-x: auto;
  white-space: nowrap;
}
.tiny {
  margin: var(--s12) 0 0;
  font-size: 11.5px;
  display: flex;
  align-items: center;
  gap: var(--s8);
}
.type {
  margin-left: auto;
}
.term {
  font-size: 15px;
}
.raw {
  margin-left: 6px;
  font-size: 11.5px;
}
.reason + .reason {
  margin-left: 4px;
}
</style>
