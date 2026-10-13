<script setup lang="ts">
import svg from '../../public/illustrations/hero-flow.svg?raw'
</script>

<template>
  <div
    class="hero-flow-host"
    role="img"
    aria-label="Messages move from emit into a queue. High and default go straight through. A scheduled message waits. One message fails and returns as a retry."
    v-html="svg"
  />
</template>

<style scoped>
.hero-flow-host {
  width: min(100%, 520px);
}

.hero-flow-host :deep(svg) {
  width: 100%;
  height: auto;
  display: block;
}
</style>
