<script setup lang="ts">
/**
 * The word's place on the acquisition ladder, as five dots.
 *
 * A deliberate mirror of the app's own `mobile/lib/ui/ladder_dots.dart` (кадры 16d/16e) — same five
 * rungs, same fold, same three dot states. The point of this screen is to watch on a laptop what the
 * phone is showing, so a second visual vocabulary here would make the two impossible to compare.
 *
 *  * PASSED — an outline. The rung is done, so the dot is spent.
 *  * CURRENT — filled, one pixel larger. The only solid ink in the row, which is what makes it
 *    findable at a glance down a table of twenty words.
 *  * AHEAD — pale. Present because the shape of the whole ladder is the information.
 *
 * `step` null means the word is OUTSIDE the ladder (a triage «знаю») and draws the dash instead —
 * five pale dots would say "at the very beginning", the opposite of what «знаю» means.
 */
import { computed } from 'vue'

const props = withDefaults(defineProps<{ step: number | null; size?: number; gap?: number }>(), {
  size: 6,
  gap: 5,
})

/**
 * The rungs the dots stand for. Rungs 1 and 2 are both «узнавание» and share one dot: the row is
 * about how far the word has come, and the direction a recognition asked in is not that.
 */
const RUNGS = [0, 1, 3, 4, 5]

/** Which dot is lit for a rung — rung 2 folds into rung 1's dot. */
function indexFor(step: number): number {
  const folded = step === 2 ? 1 : step
  const at = RUNGS.indexOf(folded)
  return at < 0 ? 0 : at
}

const current = computed(() => (props.step === null ? -1 : indexFor(props.step)))

const LABELS = ['интро', 'узнавание', 'сборка', 'ввод', 'диктант']
const title = computed(() =>
  props.step === null ? 'вне лестницы — «знаю»' : `ступень ${props.step} — ${LABELS[current.value]}`,
)
</script>

<template>
  <span v-if="step === null" class="known" title="вне лестницы — самооценка «знаю»">
    <i class="dash" />
    <span class="known-label">знаю</span>
  </span>
  <span v-else class="dots" :style="{ gap: gap + 'px' }" :title="title">
    <i
      v-for="(rung, i) in RUNGS"
      :key="rung"
      class="dot"
      :class="i < current ? 'passed' : i === current ? 'current' : 'ahead'"
      :style="{ width: (i === current ? size + 1 : size) + 'px', height: (i === current ? size + 1 : size) + 'px' }"
    />
  </span>
</template>

<style scoped>
.dots {
  display: inline-flex;
  align-items: center;
}
.dot {
  display: inline-block;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot.current {
  background: var(--ink);
}
.dot.passed {
  background: transparent;
  box-shadow: inset 0 0 0 1px var(--track);
}
.dot.ahead {
  background: var(--track);
}
.known {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--tertiary);
}
.dash {
  display: inline-block;
  width: 11px;
  height: 1px;
  background: var(--track);
}
.known-label {
  font-size: 11.5px;
  font-weight: 600;
}
</style>
