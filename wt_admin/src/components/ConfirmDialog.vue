<script setup lang="ts">
// Centred confirmation over a scrim — paper alert surface, rounded. Used for the one
// mutation in v1 (premium toggle). Confirm can show a pending state.
import PaperButton from './PaperButton.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    message?: string
    confirmLabel?: string
    cancelLabel?: string
    pending?: boolean
    destructive?: boolean
  }>(),
  { confirmLabel: 'Подтвердить', cancelLabel: 'Отмена', pending: false, destructive: false },
)
const emit = defineEmits<{ confirm: []; cancel: [] }>()
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="scrim" @click.self="!pending && emit('cancel')">
        <div class="alert" role="dialog" aria-modal="true">
          <h3 class="title">{{ title }}</h3>
          <p v-if="message" class="msg muted">{{ message }}</p>
          <div class="actions">
            <PaperButton variant="ghost" :disabled="pending" @click="emit('cancel')">
              {{ cancelLabel }}
            </PaperButton>
            <PaperButton
              :variant="destructive ? 'destructive' : 'primary'"
              :disabled="pending"
              @click="emit('confirm')"
            >
              {{ pending ? '…' : confirmLabel }}
            </PaperButton>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  background: var(--scrim);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: var(--s16);
}
.alert {
  width: 320px;
  max-width: 100%;
  background: var(--alert-surface);
  border-radius: var(--r-alert);
  box-shadow: var(--shadow-menu);
  padding: var(--s22);
  text-align: center;
}
.title {
  font-size: 19px;
  font-weight: 800;
}
.msg {
  margin: var(--s8) 0 0;
  font-size: 14px;
  line-height: 1.45;
}
.actions {
  display: flex;
  gap: var(--s12);
  justify-content: center;
  margin-top: var(--s22);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
