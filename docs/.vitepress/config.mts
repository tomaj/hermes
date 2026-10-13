import { defineConfig } from 'vitepress'
import { navEn, sidebar5x } from '../site-nav'
import { seoHead, siteUrl } from './seo'

const descriptionEn = 'PHP library for background jobs on Redis, RabbitMQ, and Amazon SQS'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: 'Hermes',
  description: descriptionEn,
  base: '/hermes/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/hermes/favicon.svg' }],
    ['meta', { name: 'theme-color', content: '#4f46e5' }],
  ],

  lastUpdated: true,

  sitemap: {
    hostname: siteUrl,
  },

  transformHead({ pageData, siteConfig }) {
    return seoHead(pageData, siteConfig.srcDir, descriptionEn)
  },


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
