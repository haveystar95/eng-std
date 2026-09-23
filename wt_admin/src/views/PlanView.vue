<script setup lang="ts">
/**
 * THE LEARNER'S PLAN PAGE (наряд ADM-1) — read-only. One long page in sections, a sticky contents
 * on the left, and a filter on top — «весь план / день N» — that narrows every section at once. The
 * day lives in the query string (`?day=2`), so a link opens exactly what its sender was looking at.
 * Every block has «JSON» (the endpoint's raw answer) and «скачать .json». What the database does not
 * hold is «н/д», never an estimate.
 */
import { computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import StatusChip from '@/components/StatusChip.vue'
import { langLabel } from '@/utils/languages'
import { absoluteTime } from '@/utils/format'
import { usdOrNa } from '@/utils/planLabels'
import CallsSection from './plan/CallsSection.vue'
import ConversationsSection from './plan/ConversationsSection.vue'
import DaysSection from './plan/DaysSection.vue'
import IssuesSection from './plan/IssuesSection.vue'
import LessonSection from './plan/LessonSection.vue'
import MoneySection from './plan/MoneySection.vue'
import PassageSection from './plan/PassageSection.vue'
import PipelineSection from './plan/PipelineSection.vue'
import PlanBlock from './plan/PlanBlock.vue'
import { jsonName } from './plan/usePlanSection'

const props = defineProps<{ code: string }>()
const route = useRoute()
const router = useRouter()

const { data: plan, loading, error, run } = useAsync(() => api.getPlan(props.code))
onMounted(run)
watch(() => props.code, run)

/** The filter: null — the whole plan. */
const day = computed<number | null>(() => {
  const n = Number(route.query.day)
  return Number.isInteger(n) && n >= 1 ? n : null
})
function setDay(next: number | null) {
  router.replace({ query: { ...route.query, day: next === null ? undefined : String(next) } })
}
const dayNumbers = computed(() => Array.from({ length: plan.value?.progress.days_total ?? 0 }, (_, i) => i + 1))

const CONTENTS = [
  { id: 'header', label: 'Шапка' },
  { id: 'issues', label: 'Что не так' },
  { id: 'days', label: 'Обзор' },
  { id: 'pipeline', label: 'Конвейер' },
  { id: 'lesson', label: 'Урок и голос' },
  { id: 'passage', label: 'Прохождение' },
  { id: 'conversations', label: 'Разговоры' },
  { id: 'money', label: 'Деньги' },
  { id: 'calls', label: 'Вызовы API' },
]

/** Sections load by the canonical code, whatever the URL named the plan by. */
const code = computed(() => plan.value?.code ?? props.code)
</script>

<template>
  <div class="plan-page">
    <Breadcrumbs
      :items="[
        { label: 'Пользователи', to: { name: 'users' } },
        ...(plan?.user ? [{ label: plan.user.email ?? plan.user.name, to: { name: 'user', params: { id: plan.user.id, tab: 'plans' } } }] : []),
        { label: `План ${plan?.code ?? code}` },
      ]"
    />

    <div class="layout">
      <nav class="toc" aria-label="Разделы">
        <a v-for="c in CONTENTS" :key="c.id" :href="`#${c.id}`" class="toc-link">{{ c.label }}</a>
      </nav>

      <main class="content">
        <div class="filter" role="toolbar" aria-label="Фильтр по дню">
          <button class="pill" :class="{ on: day === null }" @click="setDay(null)">весь план</button>
          <button v-for="n in dayNumbers" :key="n" class="pill" :class="{ on: day === n }" @click="setDay(n)">день {{ n }}</button>
        </div>

        <PlanBlock
          id="header"
          :title="plan ? `${plan.code} · ${plan.title_native ?? 'без названия'}` : `План ${code}`"
          :subtitle="plan?.goal_text"
          :raw="plan"
          :filename="jsonName(code, 'header', null)"
          :loading="loading"
          :error="error"
          @retry="run"
        >
          <template v-if="plan">
            <div class="head-grid">
              <div class="kv">
                <span class="section-label">Ученик</span>
                <RouterLink v-if="plan.user" :to="{ name: 'user', params: { id: plan.user.id, tab: 'plans' } }">{{ plan.user.email ?? plan.user.name }}</RouterLink>
                <span v-else class="faint">{{ plan.user_id }}</span>
              </div>
              <div class="kv">
                <span class="section-label">Языки · уровень</span>
                <span>{{ langLabel(plan.native_lang) }} → {{ langLabel(plan.target_lang) }} · {{ plan.level }}</span>
              </div>
              <div class="kv">
                <span class="section-label">Событие</span>
                <span class="tnum">{{ plan.event_date ?? 'без даты' }}</span>
              </div>
              <div class="kv">
                <span class="section-label">Статус</span>
                <span><StatusChip :status="plan.status" /> <span v-if="plan.status !== plan.stored_status" class="faint">в базе {{ plan.stored_status }}</span></span>
              </div>
              <div class="kv">
                <span class="section-label">Создан</span>
                <span class="tnum">{{ absoluteTime(plan.created_at) }}</span>
              </div>
              <div class="kv">
                <span class="section-label">Прогресс</span>
                <span class="tnum">день {{ plan.progress.current_day ?? plan.progress.days_total }} из {{ plan.progress.days_total }} · открыт {{ plan.progress.days_opened }} · пройдено {{ plan.progress.days_closed }}</span>
              </div>
              <div class="kv">
                <span class="section-label">Итого $</span>
                <span class="tnum big">{{ usdOrNa(plan.cost_usd) }}</span>
              </div>
            </div>
            <dl class="versions">
              <dt>Промт плана</dt>
              <dd>{{ plan.versions.plan ?? 'н/д' }} · {{ plan.models.plan ?? 'н/д' }}</dd>
              <dt>Промт дня</dt>
              <dd>{{ plan.versions.lesson.join(', ') || 'н/д' }} · {{ plan.models.lesson.join(', ') || 'н/д' }}</dd>
              <dt>P2R</dt>
              <dd>{{ plan.versions.repair ?? 'н/д' }}</dd>
              <dt>Судья швов</dt>
              <dd>{{ plan.versions.seam_judge ?? 'н/д' }}</dd>
              <dt>Судья слотов</dt>
              <dd>{{ plan.versions.slot_judge.join(', ') || 'н/д' }}</dd>
              <dt>Агент разговора</dt>
              <dd>{{ plan.versions.conversation.join(', ') || 'н/д' }}</dd>
              <dt>Сборка сервера</dt>
              <dd class="tnum">план {{ plan.build_versions.plan ?? 'н/д' }} · уроки {{ plan.build_versions.lessons.join(', ') || 'н/д' }}</dd>
            </dl>
            <ul class="nd">
              <li v-for="(n, i) in plan.not_stored" :key="i">не хранится: {{ n }}</li>
            </ul>
          </template>
        </PlanBlock>

        <template v-if="plan">
          <IssuesSection :code="code" :day="day" />
          <DaysSection :code="code" :day="day" />
          <PipelineSection :code="code" :day="day" />
          <LessonSection :code="code" :day="day" />
          <PassageSection :code="code" :day="day" />
          <ConversationsSection :code="code" :day="day" />
          <MoneySection :code="code" :day="day" />
          <CallsSection :code="code" :day="day" />
        </template>
      </main>
    </div>
  </div>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: 170px minmax(0, 1fr);
  gap: var(--s26);
  align-items: start;
}
.toc {
  position: sticky;
  top: var(--s22);
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.toc-link {
  font-size: 13px;
  font-weight: 600;
  color: var(--secondary);
  padding: 5px 10px;
  border-radius: var(--r-small);
}
.toc-link:hover {
  color: var(--ink);
  background: var(--faint-ink);
}
.filter {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: var(--s12) 0;
  background: var(--paper);
}
.pill {
  border: 1px solid var(--hairline);
  background: var(--surface-raised);
  border-radius: var(--r-pill);
  padding: 5px 13px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
}
.pill.on {
  color: var(--paper);
  background: var(--ink);
  border-color: var(--ink);
}
.head-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--s16);
}
.kv {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}
.big {
  font-size: 20px;
  font-weight: 700;
}
.versions {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 12px;
  margin: 0;
  font-size: 12.5px;
}
.versions dt {
  color: var(--tertiary);
}
.versions dd {
  margin: 0;
}
.nd {
  margin: 0;
  padding-left: 16px;
  font-size: 11.5px;
  color: var(--tertiary);
}
@media (max-width: 900px) {
  .layout {
    grid-template-columns: 1fr;
  }
  .toc {
    position: static;
    flex-direction: row;
    flex-wrap: wrap;
  }
}
</style>
