<script setup lang="ts">
// «послушать»: the sound is fetched with the panel's token (an <audio src> cannot carry it) and
// played from an object URL. Only the plan's own sounds are served — the server checks.
import { onBeforeUnmount, ref } from 'vue'
import { api } from '@/api'

const props = defineProps<{ code: string; audioId: string }>()
const state = ref<'idle' | 'loading' | 'playing' | 'error'>('idle')
let url: string | null = null
let audio: HTMLAudioElement | null = null

async function play() {
  try {
    if (!url) {
      state.value = 'loading'
      url = URL.createObjectURL(await api.getPlanAudio(props.code, props.audioId))
    }
    audio?.pause()
    audio = new Audio(url)
    audio.onended = () => (state.value = 'idle')
    state.value = 'playing'
    await audio.play()
  } catch {
    state.value = 'error'
  }
}

onBeforeUnmount(() => {
  audio?.pause()
  if (url) URL.revokeObjectURL(url)
})
</script>

<template>
  <button class="listen" :class="state" :disabled="state === 'loading'" @click="play">
    {{ state === 'loading' ? '…' : state === 'playing' ? '▶ играет' : state === 'error' ? 'нет файла' : '▶ послушать' }}
  </button>
</template>

<style scoped>
.listen {
  border: 1px solid var(--hairline);
  background: transparent;
  border-radius: var(--r-pill);
  padding: 2px 9px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--secondary);
  cursor: pointer;
  white-space: nowrap;
}
.listen:hover,
.listen.playing {
  color: var(--ink);
  border-color: var(--ink);
}
.listen.error {
  color: var(--verdict-unknown);
}
</style>
