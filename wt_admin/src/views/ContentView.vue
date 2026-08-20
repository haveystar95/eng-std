<script setup lang="ts">
/**
 * Раздел «Контент» — три уровня на одном маршруте: сводка → коллекция → паспорт термина.
 *
 * Уровень читается из query string (`?collection=…&term=…`), как на экране лестницы: ссылка на
 * «паспорт вот этого термина» открывает ровно его, а кнопка «Назад» в браузере поднимает на уровень
 * выше, а не выкидывает из раздела.
 *
 * Поллинга нет и не должно быть. Здоровье контента меняется только когда кто-то прогнал станок или
 * поправил термин — то есть по действию человека, а не сама собой; таблица, перечитывающая себя раз
 * в пять секунд, тут покупает только мигание. Обновление — кнопкой.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaperButton from '@/components/PaperButton.vue'
import TermContentPassport from '@/components/TermContentPassport.vue'
import ContentCollection from '@/views/content/ContentCollection.vue'
import ContentSummary from '@/views/content/ContentSummary.vue'

const route = useRoute()
const router = useRouter()

const collectionId = computed(() => (route.query.collection as string) ?? '')
const termId = computed(() => (route.query.term as string) ?? '')
const level = computed<'summary' | 'collection' | 'term'>(() =>
  termId.value ? 'term' : collectionId.value ? 'collection' : 'summary',
)

// Remounting is the refresh: every level loads once on mount, so bumping the key re-asks the
// server without each child having to expose a reload of its own.
const reloadKey = ref(0)
const collectionTitle = ref('')
watch(collectionId, () => (collectionTitle.value = ''))

function openCollection(id: string): void {
  void router.push({ name: 'content', query: { collection: id } })
}
function openTerm(id: string): void {
  void router.push({ name: 'content', query: { collection: collectionId.value || undefined, term: id } })
}

const crumbs = computed(() => {
  const items: { label: string; to?: { name: string; query?: Record<string, string> } }[] = [
    { label: 'Контент', to: level.value === 'summary' ? undefined : { name: 'content' } },
  ]
  if (collectionId.value) {
    items.push({
      label: collectionTitle.value || 'Коллекция',
      to: level.value === 'term' ? { name: 'content', query: { collection: collectionId.value } } : undefined,
    })
  }
  if (termId.value) items.push({ label: 'Паспорт термина' })
  return items
})

const subtitle = computed(() =>
  level.value === 'term'
    ? 'Что позволяет собрать контент термина. Когда тренажёр откроется ученику — вопрос лестницы, экран «Прогресс обучения».'
    : 'Чем словарь укомплектован: примеры, дистракторы, варианты — и что из этого соберётся в карточку.',
)
</script>

<template>
  <div>
    <Breadcrumbs :items="crumbs" />
    <PageHeader title="Здоровье контента" :subtitle="subtitle">
      <template #actions>
        <PaperButton variant="quiet" small @click="reloadKey++">Обновить</PaperButton>
      </template>
    </PageHeader>

    <ContentSummary v-if="level === 'summary'" :key="`s${reloadKey}`" @open="openCollection" />

    <ContentCollection
      v-else-if="level === 'collection'"
      :key="`c${reloadKey}-${collectionId}`"
      :collection-id="collectionId"
      @open="openTerm"
      @title="(t) => (collectionTitle = t)"
    />

    <TermContentPassport v-else :key="`t${reloadKey}-${termId}`" :term-id="termId" />
  </div>
</template>
