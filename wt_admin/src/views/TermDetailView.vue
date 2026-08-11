<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { count } from '@/utils/format'
import { langLabel } from '@/utils/labels'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import Badge from '@/components/Badge.vue'
import ImageThumb from '@/components/ImageThumb.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps<{ id: string }>()
const { data, loading, error, run } = useAsync(() => api.getTerm(props.id))
onMounted(run)
</script>

<template>
  <div>
    <PageHeader :title="data?.text ?? 'Термин'" :back="{ to: { name: 'terms' }, label: 'Термины' }" />
    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="data">
      <div class="head-row">
        <ImageThumb v-if="data.imageUrl" :url="data.imageUrl" :size="96" :alt="data.text" />
        <div class="meta">
          <Badge>{{ data.type }}</Badge>
          <Badge>{{ langLabel(data.lang) }}</Badge>
          <span v-if="data.ipa" class="faint">{{ data.ipa }}</span>
          <span v-if="data.pos" class="faint">· {{ data.pos }}</span>
          <span class="faint">· охват: {{ count(data.progressCount) }} прогресса</span>
        </div>
      </div>

      <div class="grid">
        <PaperCard>
          <div class="section-label">Переводы</div>
          <ul class="list">
            <li v-for="(tr, i) in data.translations" :key="i">
              <span class="muted">{{ tr.text }}</span>
              <Badge v-if="tr.isPrimary" tone="known" class="pri">осн.</Badge>
            </li>
            <li v-if="data.translations.length === 0" class="faint">—</li>
          </ul>
        </PaperCard>

        <PaperCard>
          <div class="section-label">Входит в коллекции</div>
          <ul class="list">
            <li v-for="c in data.collections" :key="c.id">
              <RouterLink :to="{ name: 'collection', params: { id: c.id } }" class="clink serif">
                {{ c.title }}
              </RouterLink>
            </li>
            <li v-if="data.collections.length === 0" class="faint">—</li>
          </ul>
        </PaperCard>

        <PaperCard v-if="data.examples.length" class="examples">
          <div class="section-label">Примеры</div>
          <ul class="list">
            <li v-for="(ex, i) in data.examples" :key="i" class="ex">
              <span class="serif ex-en">{{ ex.sentence }}</span>
              <span v-if="ex.translation" class="ex-ru faint">{{ ex.translation }}</span>
            </li>
          </ul>
        </PaperCard>
      </div>
    </template>
  </div>
</template>

<style scoped>
.head-row {
  display: flex;
  align-items: center;
  gap: var(--s16);
  margin-bottom: var(--s16);
}
.meta {
  display: flex;
  align-items: center;
  gap: var(--s12);
  flex-wrap: wrap;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--s16);
}
.examples {
  grid-column: 1 / -1;
}
.list {
  list-style: none;
  margin: var(--s12) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--s8);
}
.pri {
  margin-left: var(--s8);
}
.clink {
  font-size: 16px;
  color: var(--ink);
  border-bottom: 1px solid var(--track);
}
.clink:hover {
  border-color: var(--ink);
}
.ex {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 0;
  border-top: 1px solid var(--divider-faint);
}
.ex:first-child {
  border-top: none;
}
.ex-en {
  font-style: italic;
  font-size: 15px;
  color: var(--ink-body);
}
.ex-ru {
  font-size: 13px;
}
</style>
