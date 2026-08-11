<script setup lang="ts">
// Small caps badge. `tone` maps to the paper palette; the three verdict tones are
// the only chromatic colour in the UI. Default is a quiet hairline-outlined chip.
withDefaults(
  defineProps<{ tone?: 'neutral' | 'known' | 'unsure' | 'unknown'; solid?: boolean }>(),
  { tone: 'neutral', solid: false },
)
</script>

<template>
  <span class="badge" :class="[tone, { solid }]">
    <slot />
  </span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: var(--r-pill);
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
  border: 1px solid var(--hairline);
  color: var(--secondary);
  background: transparent;
}
.badge.known {
  color: var(--verdict-known);
  border-color: color-mix(in srgb, var(--verdict-known) 40%, transparent);
}
.badge.unsure {
  color: var(--verdict-unsure);
  border-color: color-mix(in srgb, var(--verdict-unsure) 40%, transparent);
}
.badge.unknown {
  color: var(--verdict-unknown);
  border-color: color-mix(in srgb, var(--verdict-unknown) 40%, transparent);
}
.badge.solid {
  border-color: transparent;
}
.badge.solid.known {
  background: var(--verdict-known);
  color: var(--on-verdict-known);
}
.badge.solid.unsure {
  background: var(--verdict-unsure);
  color: var(--on-verdict-unsure);
}
.badge.solid.unknown {
  background: var(--verdict-unknown);
  color: var(--on-verdict-unknown);
}
.badge.solid.neutral {
  background: var(--faint-ink);
  color: var(--ink);
}
</style>
