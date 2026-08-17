<script setup lang="ts">
// A small image thumbnail that links to the full image (opens in a new tab). When no
// URL is present it renders a quiet placeholder box, so rows stay aligned.
withDefaults(defineProps<{ url?: string | null; size?: number; alt?: string }>(), {
  url: null,
  size: 46,
  alt: '',
})
</script>

<template>
  <a
    v-if="url"
    class="thumb"
    :href="url"
    target="_blank"
    rel="noopener noreferrer"
    :title="'Открыть изображение'"
    :style="{ width: size + 'px', height: Math.round(size * 0.7) + 'px' }"
    @click.stop
  >
    <img :src="url" :alt="alt" loading="lazy" />
  </a>
  <span
    v-else
    class="thumb empty"
    :style="{ width: size + 'px', height: Math.round(size * 0.7) + 'px' }"
    title="Нет изображения"
    aria-hidden="true"
  >—</span>
</template>

<style scoped>
.thumb {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--r-thumb);
  overflow: hidden;
  background: var(--faint-ink);
  flex-shrink: 0;
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.thumb.empty {
  color: var(--tertiary);
  font-size: 12px;
  border: 1px dashed var(--dashed);
  background: transparent;
}
</style>
