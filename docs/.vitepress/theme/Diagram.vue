<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { withBase } from 'vitepress'

defineProps<{ name: string, alt: string }>()

const open = ref(false)
const trigger = ref<HTMLButtonElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

async function show() {
  open.value = true
  document.addEventListener('keydown', onKey)
  document.documentElement.style.overflow = 'hidden'
  await nextTick()
  closeButton.value?.focus()
}

function close() {
  if (!open.value) return
  open.value = false
  document.removeEventListener('keydown', onKey)
  document.documentElement.style.overflow = ''
  trigger.value?.focus()
}

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.documentElement.style.overflow = ''
})
</script>

<template>
  <figure class="hand-diagram">
    <button ref="trigger" type="button" class="hand-diagram-zoom" :aria-label="`Enlarge diagram: ${alt}`" @click="show">
      <img class="hand-diagram-light" :src="withBase(`/diagrams/${name}-light.svg`)" :alt="alt" loading="lazy" decoding="async">
      <img class="hand-diagram-dark" :src="withBase(`/diagrams/${name}-dark.svg`)" :alt="alt" loading="lazy" decoding="async">
    </button>
    <Teleport to="body">
      <div v-if="open" class="hand-diagram-lightbox" role="dialog" aria-modal="true" :aria-label="alt" @click="close">
        <button ref="closeButton" type="button" class="hand-diagram-close" aria-label="Close diagram" @click.stop="close">×</button>
        <img class="hand-diagram-light" :src="withBase(`/diagrams/${name}-light.svg`)" :alt="alt">
        <img class="hand-diagram-dark" :src="withBase(`/diagrams/${name}-dark.svg`)" :alt="alt">
      </div>
    </Teleport>
  </figure>
</template>
