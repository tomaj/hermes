<script setup lang="ts">
import { withBase } from 'vitepress'

const cases = [
  { icon: 'email', title: 'Send emails', text: 'Signup, password reset, or order mails go out from the worker. The request returns before the SMTP server even answers.' },
  { icon: 'invoice', title: 'Invoices and PDFs', text: 'Render the PDF, store it, and attach it to an email in the background. The user clicks once and moves on.' },
  { icon: 'thumbnails', title: 'Images and video', text: 'Resize uploads, cut thumbnails, or start a transcode. Heavy CPU work stays off the web servers.' },
  { icon: 'data', title: 'Data processing', text: 'Recalculate stats, aggregate reports, or clean up records in small messages that several workers share.' },
  { icon: 'csv', title: 'Imports and exports', text: 'Parse a large CSV or build an export file row by row. Tell the user when it is ready instead of making them wait.' },
  { icon: 'webhooks', title: 'Webhooks', text: 'Deliver outgoing webhooks from a worker. RetryTrait tries again with a growing delay when the receiver is down.' },
  { icon: 'sync', title: 'Third-party APIs', text: 'Sync orders to an ERP, push contacts to a CRM, or call a slow payment API without holding a PHP-FPM process.' },
  { icon: 'notify', title: 'Notifications', text: 'Fan out push, SMS, or in-app notifications to many users from one emitted message.' },
  { icon: 'search', title: 'Search indexing', text: 'Update Elasticsearch, Meilisearch, or a search table after a save, so the write itself stays fast.' },
  { icon: 'cache', title: 'Cache warming', text: 'Rebuild expensive cache entries after a deploy or a content change, before the next visitor asks for them.' },
  { icon: 'reminder', title: 'Delayed jobs', text: 'Send a reminder in 24 hours or expire a reservation in 15 minutes with executeAt on Redis or Predis.' },
  { icon: 'jobs', title: 'Any slow task', text: 'If it does not have to finish before the response, emit a message and let a handler do it.' },
]
</script>

<template>
  <section class="use-cases" aria-labelledby="use-cases-title">
    <div class="container">
      <h2 id="use-cases-title">What people use it for</h2>
      <p class="lead">The web request emits a message and answers right away. A worker does the slow part.</p>
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
