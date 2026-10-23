<script setup lang="ts">
import { withBase } from 'vitepress'

import { computed } from 'vue'
import { useStrings } from './i18n'

const icons = ['email', 'invoice', 'thumbnails', 'data', 'csv', 'webhooks', 'sync', 'notify', 'search', 'cache', 'reminder', 'jobs']
const t = useStrings()
const cases = computed(() => icons.map((icon) => ({ icon, title: t.value.useCases[icon][0], text: t.value.useCases[icon][1] })))
</script>

<template>
  <section class="use-cases" aria-labelledby="use-cases-title">
    <div class="container">
      <h2 id="use-cases-title">{{ t.useCasesTitle }}</h2>
      <p class="lead">{{ t.useCasesLead }}</p>
      <ul class="grid">
        <li v-for="c in cases" :key="c.icon" class="card">
          <span class="icon">
            <img class="light" :src="withBase(`/diagrams/icon-${c.icon}-light.svg`)" alt="" width="44" height="44" loading="lazy">
            <img class="dark" :src="withBase(`/diagrams/icon-${c.icon}-dark.svg`)" alt="" width="44" height="44" loading="lazy">
          </span>
          <h3>{{ c.title }}</h3>
          <p>{{ c.text }}</p>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.use-cases { padding: 8px 24px 48px; }
@media (min-width: 640px) { .use-cases { padding: 8px 48px 56px; } }
@media (min-width: 960px) { .use-cases { padding: 8px 64px 64px; } }
.container { max-width: 1152px; margin: 0 auto; }
h2 {
  font-family: 'Excalifont', var(--vp-font-family-base);
  font-size: 30px; font-weight: 400; line-height: 1.3; margin: 0 0 6px;
}
.lead { color: var(--vp-c-text-2); margin: 0 0 24px; }
.grid {
  list-style: none; padding: 0; margin: 0;
  display: grid; gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
}
.card {
  border: 1.5px dashed var(--vp-c-divider);
  border-radius: 14px;
  padding: 18px 18px 16px;
  transition: border-color .2s, transform .2s;
}
.card:hover { border-color: var(--vp-c-brand-1); transform: translateY(-2px); }
.icon { display: block; width: 44px; height: 44px; margin-bottom: 8px; }
.icon img { width: 44px; height: 44px; }
h3 { font-family: 'Excalifont', var(--vp-font-family-base); font-size: 20px; font-weight: 400; margin: 0 0 4px; }
.card p { margin: 0; font-size: 14px; line-height: 1.55; color: var(--vp-c-text-2); }
@media (prefers-reduced-motion: reduce) { .card, .card:hover { transition: none; transform: none; } }
</style>
