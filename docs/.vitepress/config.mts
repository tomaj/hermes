import { defineConfig } from 'vitepress'
import { navEn, sidebar5x } from '../site-nav'

const descriptionEn = 'PHP library for background jobs on Redis, RabbitMQ, and Amazon SQS'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: 'Hermes',
  description: descriptionEn,
  base: '/hermes/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/hermes/favicon.svg' }],
    ['meta', { name: 'theme-color', content: '#4f46e5' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Hermes - background jobs for PHP' }],
    ['meta', { property: 'og:description', content: descriptionEn }],
    ['meta', { property: 'og:url', content: 'https://tomaj.github.io/hermes/' }],
    ['meta', { property: 'og:image', content: 'https://tomaj.github.io/hermes/og.svg' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:title', content: 'Hermes - background jobs for PHP' }],
    ['meta', { name: 'twitter:description', content: descriptionEn }],
    ['meta', { name: 'twitter:image', content: 'https://tomaj.github.io/hermes/og.svg' }],
  ],

  lastUpdated: true,


  // English is the only locale today. A `sk` locale can be added beside `root`
  // without moving these pages. See guide/versioning.
  locales: {
    root: {
      label: 'English',
      lang: 'en-US',
      description: descriptionEn,
    },
  },

  themeConfig: {
    logo: {
      light: '/logo-light.svg',
      dark: '/logo-dark.svg',
    },

    nav: navEn,

    sidebar: {
      '/': sidebar5x,
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/tomaj/hermes' },
    ],

    editLink: {
      pattern: 'https://github.com/tomaj/hermes/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2015-2026 Tomas Majer',
    },
  },
})
