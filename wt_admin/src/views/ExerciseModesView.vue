<script setup lang="ts">
// The product default: which trainers every user gets unless they have their own override.
//
// A global flip touches everyone, so it goes through a confirmation that says what will change
// rather than a bare "are you sure" — the diff is the useful part.
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { modeLabel } from '@/utils/labels'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ModeToggles from '@/components/ModeToggles.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import StateBlock from '@/components/StateBlock.vue'
import type { ExerciseMode } from '@/api/types'

const { data, loading, error, run } = useAsync(() => api.getExerciseModes())
onMounted(run)

const draft = ref<ExerciseMode[]>([])
const saving = ref(false)
const confirming = ref(false)
const saveError = ref<string | null>(null)

// The draft follows a fresh load, so it never shows a stale set after a reload or a save.
watch(data, (v) => {
  if (v) draft.value = [...v.global]
})

const saved = computed(() => data.value?.global ?? [])
const dirty = computed(() => draft.value.join(',') !== saved.value.join(','))
const turningOff = computed(() => saved.value.filter((m) => !draft.value.includes(m)))
const turningOn = computed(() => draft.value.filter((m) => !saved.value.includes(m)))
const labels = (modes: ExerciseMode[]) => modes.map(modeLabel).join(', ')

function reset() {
  draft.value = [...saved.value]
  saveError.value = null
}

async function save() {
  saving.value = true
  saveError.value = null
  try {
    data.value = await api.setExerciseModes(draft.value)
    confirming.value = false
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить'
    confirming.value = false
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Тренажёры"
      subtitle="Какие упражнения получают пользователи. Порядок задаёт ротацию в свободной тренировке."
    />

    <StateBlock v-if="loading && !data" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />

    <template v-else-if="data">
      <PaperCard class="card">
        <div class="section-label">Общий набор</div>
        <p class="hint faint">
          Действует на всех, у кого нет персональной настройки. Новый тренажёр появляется здесь
          выключенным — включаем сначала себе, потом бете, потом всем.
        </p>

        <ModeToggles v-model="draft" :available="data.available" :disabled="saving" />

        <p v-if="!draft.length" class="warn">
          Нужен хотя бы один тренажёр — общему набору не от кого наследовать.
        </p>
        <p v-if="saveError" class="warn">{{ saveError }}</p>

        <div class="actions">
          <PaperButton variant="ghost" :disabled="!dirty || saving" @click="reset">Отменить</PaperButton>
          <PaperButton :disabled="!dirty || !draft.length || saving" @click="confirming = true">
            Сохранить
          </PaperButton>
        </div>
      </PaperCard>

      <p class="foot faint">
        Смена доезжает на устройства ближайшим синком — переустановка не нужна. Персональные
        настройки задаются на карточке пользователя.
      </p>
    </template>

    <ConfirmDialog
      :open="confirming"
      title="Изменить общий набор?"
      :message="[
        turningOff.length ? `Выключаем: ${labels(turningOff)}.` : '',
        turningOn.length ? `Включаем: ${labels(turningOn)}.` : '',
        'Затронет всех, у кого нет персональной настройки.',
      ]
        .filter(Boolean)
        .join(' ')"
      confirm-label="Сохранить"
      :pending="saving"
      :destructive="turningOff.length > 0"
      @cancel="confirming = false"
      @confirm="save"
    />
  </div>
</template>

<style scoped>
.card {
  max-width: 560px;
}
.hint {
  margin: 6px 0 14px;
  font-size: 13px;
  line-height: 1.45;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}
.warn {
  margin: 12px 0 0;
  font-size: 13px;
  color: var(--destructive-text, #9a4b3f);
}
.foot {
  max-width: 560px;
  margin-top: 14px;
  font-size: 12px;
  line-height: 1.5;
}
</style>
