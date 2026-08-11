<script setup lang="ts">
// Buttons in the paper system. `variant`:
//   primary   — ink fill, paper text (main action)
//   quiet     — faint-ink fill, ink text (secondary)
//   ghost     — transparent, hairline border
//   destructive — destructive text/border, never a fill (rule 20)
withDefaults(
  defineProps<{
    variant?: 'primary' | 'quiet' | 'ghost' | 'destructive'
    type?: 'button' | 'submit'
    disabled?: boolean
    block?: boolean
    small?: boolean
  }>(),
  { variant: 'primary', type: 'button', disabled: false, block: false, small: false },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="pbtn"
    :class="[variant, { block, small }]"
  >
    <slot />
  </button>
</template>

<style scoped>
.pbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--s8);
  border: 1px solid transparent;
  border-radius: var(--r-button);
  padding: 10px 18px;
  font-weight: 700;
  font-size: 15px;
  line-height: 1;
  min-height: var(--s4);
  transition: filter 0.12s ease, background 0.12s ease, opacity 0.12s ease;
}
.pbtn.small {
  padding: 7px 12px;
  font-size: 13px;
  border-radius: var(--r-small);
}
.pbtn.block {
  width: 100%;
}
.pbtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.pbtn.primary {
  background: var(--ink);
  color: var(--paper);
}
.pbtn.primary:not(:disabled):hover {
  filter: brightness(1.12);
}
.pbtn.quiet {
  background: var(--faint-ink);
  color: var(--ink);
}
.pbtn.quiet:not(:disabled):hover {
  background: var(--divider-faint);
}
.pbtn.ghost {
  background: transparent;
  color: var(--ink);
  border-color: var(--hairline);
}
.pbtn.ghost:not(:disabled):hover {
  background: var(--faint-ink);
}
.pbtn.destructive {
  background: transparent;
  color: var(--destructive);
  border-color: var(--destructive);
}
.pbtn.destructive:not(:disabled):hover {
  background: rgba(154, 68, 48, 0.08);
}
</style>
