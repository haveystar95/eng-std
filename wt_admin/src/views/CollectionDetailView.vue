<script setup lang="ts">
import { onMounted } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { count } from '@/utils/format'
import { COLLECTION_SOURCE_LABEL, COLLECTION_TYPE_LABEL, langLabel } from '@/utils/labels'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{ id: string }>()
const { data, loading, error, run } = useAsync(() => api.getCollection(props.id))
onMounted(run)
</script>

<template>
  <div>
    <PageHeader
      :title="data?.title ?? 'Коллекция'"
      :back="{ to: { name: 'collections' }, label: 'Коллекции' }"
    />
    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="data">
      <div class="head-row">
        <ImageThumb v-if="data.imageUrl" :url="data.imageUrl" :size="96" :alt="data.title" />
        <div>
          <div class="meta">
            <Badge>{{ COLLECTION_TYPE_LABEL[data.type] }}</Badge>
            <Badge>{{ COLLECTION_SOURCE_LABEL[data.source] }}</Badge>
            <span class="faint">{{ langLabel(data.sourceLang) }} → {{ langLabel(data.targetLang) }}</span>
            <span class="faint">· {{ count(data.itemsCount) }} терминов</span>
          </div>
          <p v-if="data.description" class="desc muted">{{ data.description }}</p>
        </div>
      </div>

      <PaperCard :pad="false" class="wrap">
        <StateBlock v-if="data.terms.length === 0" kind="empty" title="В коллекции нет терминов" />
        <ul v-else class="terms">
          <li v-for="t in data.terms" :key="t.termId" class="term-row">
            <ImageThumb :url="t.imageUrl" :size="44" :alt="t.text" />
            <span class="serif term">{{ t.text }}</span>
            <span class="tr muted">{{ t.translation ?? '—' }}</span>
          </li>
        </ul>
      </PaperCard>
    </template>
  </div>
</template>

<style scoped>
.head-row {
  display: flex;
  align-items: flex-start;
  gap: var(--s16);
  margin-bottom: var(--s16);
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
.wrap {
  overflow: hidden;
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
}
.tr {
  font-size: 14px;
  text-align: right;
}
</style>
