<script setup lang="ts">
// One user's trainers: inherited from the product default, or their own set.
//
// "Inherits" is deliberately a STATE, not a set that happens to equal the default — a user left on
// inherit follows later changes to it, a user pinned to a copy would silently miss the next mode
// that ships. That is why the reset sends null rather than the current default.
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import { useAsync } from '@/composables/useAsync'
import { modeLabel } from '@/utils/labels'
import ModeToggles from '@/components/ModeToggles.vue'
import PaperButton from '@/components/PaperButton.vue'
import PaperCard from '@/components/PaperCard.vue'
import StateBlock from '@/components/StateBlock.vue'
import type { ExerciseMode } from '@/api/types'

const props = defineProps<{ userId: string }>()

const { data, loading, error, run } = useAsync(() => api.getUserExerciseModes(props.userId))
onMounted(run)

const draft = ref<ExerciseMode[]>([])
const custom = ref(false)
const saving = ref(false)
const saveError = ref<string | null>(null)

watch(data, (v) => {
  if (!v) return
  custom.value = !v.inherits
  draft.value = [...v.effective]
})

const savedCustom = computed(() => !(data.value?.inherits ?? true))
const savedSet = computed(() => data.value?.override ?? data.value?.global ?? [])
const dirty = computed(
  () => custom.value !== savedCustom.value || (custom.value && draft.value.join(',') !== savedSet.value.join(',')),
)
const globalLabels = computed(() => (data.value?.global ?? []).map(modeLabel).join(', '))

/** Back to the common set in one action — the same null the radio+save path sends. */
async function resetToGlobal() {
  custom.value = false
  await save()
}

async function save() {
  saving.value = true
  saveError.value = null
  try {
    // Back to inherit = null. Sending the current default instead would look identical today and
    // wrong the day the default changes.
    data.value = await api.setUserExerciseModes(props.userId, custom.value ? draft.value : null)
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <StateBlock v-if="loading && !data" kind="loading" />
    <StateBlock v-else-if="error" kind="error" :message="error" retryable @retry="run" />

    <PaperCard v-else-if="data" class="card">
      <div class="head">
        <div class="section-label">Тренажёры</div>
        <span class="badge" :class="{ custom: !data.inherits }">
          {{ data.inherits ? 'Общий набор' : 'Своя настройка' }}
        </span>
      </div>

      <div class="choice">
        <label class="opt">
          <input v-model="custom" type="radio" :value="false" :disabled="saving" />
          <span>Как у всех</span>
          <span class="faint">— {{ globalLabels }}</span>
        </label>
        <label class="opt">
          <input v-model="custom" type="radio" :value="true" :disabled="saving" />
          <span>Своя настройка</span>
        </label>
      </div>

      <ModeToggles v-if="custom" v-model="draft" :available="data.available" :disabled="saving" />

      <p v-if="custom && !draft.length" class="warn">
        Пустой набор невозможен — снимите «свою настройку», чтобы вернуть общий.
      </p>
      <p v-if="saveError" class="warn">{{ saveError }}</p>

      <div class="actions">
        <PaperButton
          v-if="!data.inherits"
          variant="ghost"
          :disabled="saving"
          @click="resetToGlobal"
        >
          Вернуть общий набор
        </PaperButton>
        <PaperButton :disabled="!dirty || (custom && !draft.length) || saving" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </PaperButton>
      </div>
    </PaperCard>
  </div>
</template>

<style scoped>
.card {
  max-width: 520px;
}
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.badge {
  font-size: 12px;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid var(--hairline);
  color: var(--secondary);
}
.badge.custom {
  border-color: var(--ink);
  color: var(--ink);
}
.choice {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 14px;
}
.opt {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
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
</style>
