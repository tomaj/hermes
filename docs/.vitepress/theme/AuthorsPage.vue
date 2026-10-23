<script setup lang="ts">
import { withBase } from 'vitepress'
import data from '../../data/authors.json'
import { useStrings } from './i18n'

const t = useStrings()

const m = data.maintainer
const others = data.contributors.filter((c) => c.login !== m.login)
const own = data.contributors.find((c) => c.login === m.login)
</script>

<template>
  <section class="authors-maintainer">
    <img :src="m.avatar" :alt="t.onGitHub(m.name)" width="96" height="96" loading="lazy">
    <div>
      <p class="authors-role">{{ t.maintainer }}</p>
      <h3 class="authors-name">{{ m.name }} <a :href="m.url">@{{ m.login }}</a></h3>
      <ul class="authors-facts">
        <li v-if="m.company">{{ t.worksAt(m.company) }}</li>
        <li v-if="m.location">{{ m.location }}</li>
        <li>{{ t.githubSince(m.githubSince) }}</li>
        <li v-if="own">{{ t.commitsToHermes(own.commits) }}</li>
      </ul>
      <p class="authors-links">
        <a :href="m.url">GitHub</a>
        <a v-if="m.blog" :href="m.blog">{{ m.blog.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '') }}</a>
        <a v-if="m.twitter" :href="`https://x.com/${m.twitter}`">X @{{ m.twitter }}</a>
      </p>
    </div>
  </section>

  <ul class="authors-grid">
    <li v-for="c in others" :key="c.login" class="authors-card">
      <a :href="c.url">
        <img :src="c.avatar" :alt="t.onGitHub(c.login)" width="48" height="48" loading="lazy">
        <span class="authors-login">{{ c.login }}</span>
        <span class="authors-commits">{{ t.commits(c.commits) }}</span>
      </a>
    </li>
    <li class="authors-card authors-you">
      <a :href="withBase(t.contributingLink)">
        <span class="authors-plus" aria-hidden="true">+</span>
        <span class="authors-login">{{ t.you }}</span>
        <span class="authors-commits">{{ t.howToContribute }}</span>
      </a>
    </li>
  </ul>
</template>

<style scoped>
.authors-maintainer {
  display: flex;
  gap: 20px;
  align-items: flex-start;
  margin: 16px 0 24px;
  padding: 20px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 14px;
  background: var(--vp-c-bg-soft);
}
.authors-maintainer img { border-radius: 50%; flex: none; }
.authors-role { margin: 0; color: var(--vp-c-text-2); font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
.authors-name { margin: 2px 0 8px !important; padding: 0 !important; border: 0 !important; font-size: 22px; }
.authors-name a { font-size: 15px; font-weight: 500; margin-left: 6px; }
.authors-facts { display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 0 !important; padding: 0 !important; list-style: none !important; color: var(--vp-c-text-2); font-size: 14px; }
.authors-facts li { margin: 0 !important; }
.authors-links { display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 10px 0 0 !important; font-size: 14px; }
.authors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
  margin: 16px 0 !important;
  padding: 0 !important;
  list-style: none !important;
}
.authors-card { margin: 0 !important; }
.authors-card a {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 16px 8px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 12px;
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  text-decoration: none;
  transition: border-color 0.2s, transform 0.2s;
}
.authors-card a:hover { border-color: var(--vp-c-brand-1); transform: translateY(-2px); }
.authors-card img { border-radius: 50%; }
.authors-login { font-weight: 600; }
.authors-commits { color: var(--vp-c-text-2); font-size: 13px; }
.authors-plus { display: grid; place-items: center; width: 48px; height: 48px; border: 2px dashed var(--vp-c-divider); border-radius: 50%; font-size: 24px; color: var(--vp-c-text-2); }
@media (max-width: 520px) { .authors-maintainer { flex-direction: column; } }
@media (prefers-reduced-motion: reduce) { .authors-card a { transition: none; } .authors-card a:hover { transform: none; } }
</style>
