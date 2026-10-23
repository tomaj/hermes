import { defineConfig } from 'vitepress'
import { navEn, navSk, sidebar5x, sidebar5xSk, sidebarEn, sidebarSk } from '../site-nav'
import { seoHead, siteUrl } from './seo'

const descriptionEn = 'PHP library for background jobs on Redis, RabbitMQ, and Amazon SQS'
const descriptionSk = 'PHP knižnica na úlohy na pozadí cez Redis, RabbitMQ a Amazon SQS'

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
    return seoHead(pageData, siteConfig.srcDir, pageData.relativePath.startsWith('sk/') ? descriptionSk : descriptionEn)
  },


  // English is the default locale at the site root; Slovak mirrors it under /sk/. See guide/versioning.
  locales: {
    root: {
      label: 'English',
      lang: 'en-US',
      description: descriptionEn,
    },
    sk: {
      label: 'Slovenčina',
      lang: 'sk',
      link: '/sk/',
      description: descriptionSk,
      themeConfig: {
        nav: navSk,
        sidebar: {
          '/sk/5.x/': sidebar5xSk,
          '/sk/': sidebarSk,
        },
        outline: {
          label: 'Na tejto stránke',
        },
        docFooter: {
          prev: 'Predchádzajúca stránka',
          next: 'Ďalšia stránka',
        },
        editLink: {
          pattern: 'https://github.com/tomaj/hermes/edit/master/docs/:path',
          text: 'Upraviť túto stránku na GitHube',
        },
        lastUpdated: {
          text: 'Posledná aktualizácia',
        },
        darkModeSwitchLabel: 'Vzhľad',
        lightModeSwitchTitle: 'Prepnúť na svetlý režim',
        darkModeSwitchTitle: 'Prepnúť na tmavý režim',
        sidebarMenuLabel: 'Ponuka',
        returnToTopLabel: 'Návrat na začiatok',
        langMenuLabel: 'Zmeniť jazyk',
        skipToContentLabel: 'Preskočiť na obsah',
        footer: {
          message: 'Vydané pod licenciou MIT.',
          copyright: 'Copyright © 2015-2026 Tomas Majer',
        },
        notFound: {
          code: '404',
          title: 'STRÁNKA SA NENAŠLA',
          quote: 'Ak nezmeníte smer a budete hľadať ďalej, môžete doraziť práve tam, kam mierite.',
          linkLabel: 'prejsť na úvod',
          linkText: 'Späť na úvod',
        },
      },
    },
  },

  themeConfig: {
    logo: {
      light: '/logo-light.svg',
      dark: '/logo-dark.svg',
    },

    nav: navEn,

    sidebar: {
      '/5.x/': sidebar5x,
      '/': sidebarEn,
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
      options: {
        locales: {
          sk: {
            translations: {
              button: {
                buttonText: 'Hľadať',
                buttonAriaLabel: 'Hľadať',
              },
              modal: {
                displayDetails: 'Zobraziť podrobný zoznam',
                resetButtonTitle: 'Vymazať hľadanie',
                backButtonTitle: 'Zavrieť hľadanie',
                noResultsText: 'Žiadne výsledky pre',
                footer: {
                  selectText: 'vybrať',
                  selectKeyAriaLabel: 'enter',
                  navigateText: 'prejsť',
                  navigateUpKeyAriaLabel: 'šípka hore',
                  navigateDownKeyAriaLabel: 'šípka dole',
                  closeText: 'zavrieť',
                  closeKeyAriaLabel: 'escape',
                },
              },
            },
          },
        },
      },
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2015-2026 Tomas Majer',
    },
  },
})
