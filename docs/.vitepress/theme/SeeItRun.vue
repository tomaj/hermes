<script setup lang="ts">
import WorkerDemo from './WorkerDemo.vue'
import { useStrings } from './i18n'

const t = useStrings()
</script>

<template>
  <section class="see-it-run">
    <div class="container vp-doc">
    <h2 id="see-it-run" tabindex="-1">{{ t.seeItRun }} <a class="header-anchor" href="#see-it-run" :aria-label="`Permalink: ${t.seeItRun}`">&#8203;</a></h2>
    <p v-html="t.seeItRunText" />
    <WorkerDemo />
    </div>
  </section>
</template>

<style scoped>
.see-it-run {
  padding: 0 24px 48px;
}

.container {
  max-width: 1152px;
  margin: 0 auto;
}

@media (min-width: 640px) {
  .see-it-run { padding: 0 48px 48px; }
}

@media (min-width: 960px) {
  .see-it-run { padding: 0 64px 48px; }
}
</style>
