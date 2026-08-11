<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { count, money } from '@/utils/format'
import { TIER_LABEL } from '@/utils/labels'
import PageHeader from '@/components/PageHeader.vue'
import PaperCard from '@/components/PaperCard.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperTabs from '@/components/PaperTabs.vue'
import Badge from '@/components/Badge.vue'
import RelativeDate from '@/components/RelativeDate.vue'
import StateBlock from '@/components/StateBlock.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PlanTab from './user/PlanTab.vue'
import ReviewsTab from './user/ReviewsTab.vue'
import CollectionsTab from './user/CollectionsTab.vue'
import DialogsTab from './user/DialogsTab.vue'
import LogsTab from './user/LogsTab.vue'
import GenerationsTab from './user/GenerationsTab.vue'

const props = defineProps<{ id: string }>()

const { data: user, loading, error, run } = useAsync(() => api.getUser(props.id))
onMounted(run)

const tab = ref<'plan' | 'reviews' | 'collections' | 'dialogs' | 'generations' | 'logs'>('plan')
const tabs = [
  { key: 'plan', label: 'План' },
  { key: 'reviews', label: 'Ревью' },
  { key: 'collections', label: 'Коллекции' },
  { key: 'dialogs', label: 'Диалоги' },
  { key: 'generations', label: 'Генерации' },
  { key: 'logs', label: 'Логи' },
]

// ── Tier toggle (the only mutation in v1) ──
const confirmOpen = ref(false)
const mutating = ref(false)
const mutateError = ref<string | null>(null)
const targetTier = computed<'free' | 'premium'>(() => (user.value?.tier === 'premium' ? 'free' : 'premium'))

function askToggle() {
  mutateError.value = null
  confirmOpen.value = true
}
async function confirmToggle() {
  if (!user.value) return
  mutating.value = true
  mutateError.value = null
  try {
    const res = await api.setTier(user.value.id, targetTier.value)
    user.value = { ...user.value, tier: res.tier }
    confirmOpen.value = false
  } catch (e) {
    mutateError.value = e instanceof Error ? e.message : 'Не удалось изменить тариф'
  } finally {
    mutating.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      :title="user?.email ?? user?.name ?? 'Пользователь'"
      :back="{ to: { name: 'users' }, label: 'Пользователи' }"
    >
      <template #actions>
        <template v-if="user">
          <Badge :tone="user.tier === 'premium' ? 'known' : 'neutral'">{{ TIER_LABEL[user.tier] }}</Badge>
          <PaperButton :variant="user.tier === 'premium' ? 'destructive' : 'primary'" small @click="askToggle">
            {{ user.tier === 'premium' ? 'Убрать Premium' : 'Сделать Premium' }}
          </PaperButton>
        </template>
      </template>
    </PageHeader>

    <StateBlock v-if="loading" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />
    <template v-else-if="user">
      <PaperCard class="profile">
        <div class="prof-grid">
          <div class="prof-item">
            <div class="section-label">Имя</div>
            <div class="prof-val">{{ user.name || '—' }}</div>
          </div>
          <div class="prof-item">
            <div class="section-label">Уровень · цель</div>
            <div class="prof-val tnum">{{ user.cefr ?? '—' }} · {{ user.dailyGoal }}/день</div>
          </div>
          <div class="prof-item">
            <div class="section-label">Часовой пояс</div>
            <div class="prof-val">{{ user.timezone ?? '—' }}</div>
          </div>
          <div class="prof-item">
            <div class="section-label">Стрик</div>
            <div class="prof-val tnum">{{ user.streakDays }} дн.</div>
          </div>
          <div class="prof-item">
            <div class="section-label">Регистрация</div>
            <div class="prof-val"><RelativeDate :value="user.createdAt" /></div>
          </div>
          <div class="prof-item">
            <div class="section-label">Онбординг</div>
            <div class="prof-val">
              <RelativeDate v-if="user.onboardedAt" :value="user.onboardedAt" />
              <span v-else class="faint">не завершён</span>
            </div>
          </div>
        </div>

        <div class="metrics">
          <div class="metric">
            <span class="m-val tnum">{{ count(user.progress.total) }}</span>
            <span class="m-lbl section-label">терминов</span>
          </div>
          <div class="metric">
            <span class="m-val tnum">{{ count(user.progress.learned) }}</span>
            <span class="m-lbl section-label">выучено</span>
          </div>
          <div class="metric">
            <span class="m-val tnum">{{ count(user.progress.mastered) }}</span>
            <span class="m-lbl section-label">усвоено</span>
          </div>
          <div class="metric">
            <span class="m-val tnum">{{ count(user.progress.dueToday) }}</span>
            <span class="m-lbl section-label">к повтору сегодня</span>
          </div>
          <div class="metric">
            <span class="m-val tnum">{{ count(user.reviewsTotal) }}</span>
            <span class="m-lbl section-label">ревью всего</span>
          </div>
          <div class="metric spend">
            <span class="m-val tnum">{{ money(user.costs.totalUsd) }}</span>
            <span class="m-lbl section-label">расходы всего</span>
          </div>
        </div>
      </PaperCard>

      <div class="tabbar">
        <PaperTabs v-model="tab" :tabs="tabs" />
      </div>

      <PlanTab v-if="tab === 'plan'" :user-id="user.id" :timezone="user.timezone" />
      <ReviewsTab v-else-if="tab === 'reviews'" :user-id="user.id" />
      <CollectionsTab v-else-if="tab === 'collections'" :collections="user.collections" />
      <DialogsTab v-else-if="tab === 'dialogs'" :user-id="user.id" />
      <GenerationsTab v-else-if="tab === 'generations'" :user-id="user.id" />
      <LogsTab v-else :user-id="user.id" />
    </template>

    <ConfirmDialog
      :open="confirmOpen"
      :title="targetTier === 'premium' ? 'Выдать Premium?' : 'Убрать Premium?'"
      :message="
        targetTier === 'premium'
          ? `Пользователю ${user?.email ?? user?.name} будет открыт премиум-доступ.`
          : `Пользователь ${user?.email ?? user?.name} лишится премиум-доступа.`
      "
      :confirm-label="targetTier === 'premium' ? 'Выдать Premium' : 'Убрать Premium'"
      :destructive="targetTier === 'free'"
      :pending="mutating"
      @confirm="confirmToggle"
      @cancel="confirmOpen = false"
    />
    <p v-if="mutateError" class="mut-err">{{ mutateError }}</p>
  </div>
</template>

<style scoped>
.profile {
  display: flex;
  flex-direction: column;
  gap: var(--s22);
}
.prof-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--s16);
}
.prof-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.prof-val {
  font-size: 15px;
  font-weight: 600;
}
.metrics {
  display: flex;
  flex-wrap: wrap;
  gap: var(--s26);
  padding-top: var(--s16);
  border-top: 1px solid var(--divider-faint);
}
.metric {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.metric.spend .m-val {
  color: var(--ink-body);
}
.m-val {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.tabbar {
  margin: var(--s26) 0 var(--s16);
}
.mut-err {
  color: var(--destructive);
  font-size: 13px;
  margin-top: var(--s12);
}
</style>
