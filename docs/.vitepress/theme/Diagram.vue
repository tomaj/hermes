<script setup lang="ts">
import { withBase } from 'vitepress'

defineProps<{ name: string, alt: string }>()
</script>

<template>
  <figure class="hand-diagram">
    <img class="hand-diagram-light" :src="withBase(`/diagrams/${name}-light.svg`)" :alt="alt" loading="lazy" decoding="async">
    <img class="hand-diagram-dark" :src="withBase(`/diagrams/${name}-dark.svg`)" :alt="alt" loading="lazy" decoding="async">
  </figure>
</template>
