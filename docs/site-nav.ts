export interface DocsNavItem {
  text: string
  link?: string
  items?: DocsNavItem[]
}

export const currentVersion = '6.x'

const coverageLink = 'https://tomaj.github.io/hermes/coverage/'
const releasesLink = 'https://github.com/tomaj/hermes/releases'

export const navEn: DocsNavItem[] = [
  { text: 'Home', link: '/' },
  { text: 'Guide', link: '/guide/getting-started' },
  { text: 'Examples', link: '/examples/' },
  { text: 'API', link: '/api/reference' },
  {
    text: currentVersion,
    items: [
      { text: '6.x (latest)', link: '/' },
      { text: '5.x', link: '/5.x/' },
      { text: 'Versioning', link: '/guide/versioning' },
    ],
  },
  { text: 'Coverage', link: coverageLink },
  { text: 'Releases', link: releasesLink },
]

export const sidebarEn: DocsNavItem[] = [
  {
    text: 'Introduction',
    items: [
      { text: 'Getting started', link: '/guide/getting-started' },
      { text: 'Concepts', link: '/guide/concepts' },
      { text: 'Architecture', link: '/guide/architecture' },
    ],
  },
  {
    text: 'Examples',
    items: [
      { text: 'Overview', link: '/examples/' },
      { text: 'Welcome email, from zero', link: '/examples/welcome-email' },
      { text: 'Invoice PDF', link: '/examples/invoice-pdf' },
      { text: 'Webhooks with retries', link: '/examples/webhooks' },
      { text: 'CSV import in chunks', link: '/examples/csv-import' },
      { text: 'Image thumbnails', link: '/examples/thumbnails' },
      { text: 'Delayed reminder', link: '/examples/reminder' },
      { text: 'Priority queues', link: '/examples/priority' },
    ],
  },
  {
    text: 'Framework integration',
    items: [
      { text: 'Overview', link: '/frameworks/' },
      { text: 'Nette', link: '/frameworks/nette' },
      { text: 'Laravel', link: '/frameworks/laravel' },
      { text: 'Symfony', link: '/frameworks/symfony' },
    ],
  },
  {
    text: 'Drivers',
    items: [
      { text: 'Overview', link: '/guide/drivers/overview' },
      { text: 'Comparison', link: '/guide/drivers/' },
      { text: 'Redis (ext-redis)', link: '/guide/drivers/redis' },
      { text: 'Predis', link: '/guide/drivers/predis' },
      { text: 'RabbitMQ', link: '/guide/drivers/rabbitmq' },
      { text: 'Amazon SQS', link: '/guide/drivers/sqs' },
      { text: 'FileSystem', link: '/guide/drivers/filesystem' },
      { text: 'Kafka', link: '/guide/drivers/kafka' },
    ],
  },
  {
    text: 'Runtime',
    items: [
      { text: 'Retry', link: '/guide/retry' },
      { text: 'Delayed execution', link: '/guide/delayed' },
      { text: 'Emitting after a commit', link: '/guide/buffered-emitter' },
      { text: 'Priority', link: '/guide/priority' },
      { text: 'Shutdown and max items', link: '/guide/shutdown' },
      { text: 'Logging', link: '/guide/logging' },
    ],
  },
  {
    text: 'Operations',
    items: [
      { text: 'Testing', link: '/guide/testing' },
      { text: 'Production deployment', link: '/guide/deployment' },
      { text: 'Extending Hermes', link: '/guide/extending' },
      { text: 'Versioning docs', link: '/guide/versioning' },
    ],
  },
  {
    text: 'Reference',
    items: [
      { text: 'API reference', link: '/api/reference' },
      { text: 'FAQ', link: '/faq' },
      { text: 'Changelog', link: '/changelog' },
      { text: 'Contributing', link: '/contributing' },
      { text: 'Authors', link: '/authors' },
    ],
  },
]

export const navSk: DocsNavItem[] = [
  { text: 'Domov', link: '/sk/' },
  { text: 'Príručka', link: '/sk/guide/getting-started' },
  { text: 'Príklady', link: '/sk/examples/' },
  { text: 'API', link: '/sk/api/reference' },
  {
    text: currentVersion,
    items: [
      { text: '6.x (aktuálna)', link: '/sk/' },
      { text: '5.x', link: '/sk/5.x/' },
      { text: 'Verzie', link: '/sk/guide/versioning' },
    ],
  },
  { text: 'Pokrytie', link: coverageLink },
  { text: 'Vydania', link: releasesLink },
]

export const sidebarSk: DocsNavItem[] = [
  {
    text: 'Úvod',
    items: [
      { text: 'Začíname', link: '/sk/guide/getting-started' },
      { text: 'Pojmy', link: '/sk/guide/concepts' },
      { text: 'Architektúra', link: '/sk/guide/architecture' },
    ],
  },
  {
    text: 'Príklady',
    items: [
      { text: 'Prehľad', link: '/sk/examples/' },
      { text: 'Uvítací e-mail od nuly', link: '/sk/examples/welcome-email' },
      { text: 'Faktúra v PDF', link: '/sk/examples/invoice-pdf' },
      { text: 'Webhooky s opakovaním', link: '/sk/examples/webhooks' },
      { text: 'Import CSV po častiach', link: '/sk/examples/csv-import' },
      { text: 'Náhľady obrázkov', link: '/sk/examples/thumbnails' },
      { text: 'Odložená pripomienka', link: '/sk/examples/reminder' },
      { text: 'Prioritné fronty', link: '/sk/examples/priority' },
    ],
  },
  {
    text: 'Integrácia do frameworkov',
    items: [
      { text: 'Prehľad', link: '/sk/frameworks/' },
      { text: 'Nette', link: '/sk/frameworks/nette' },
      { text: 'Laravel', link: '/sk/frameworks/laravel' },
      { text: 'Symfony', link: '/sk/frameworks/symfony' },
    ],
  },
  {
    text: 'Drivery',
    items: [
      { text: 'Prehľad', link: '/sk/guide/drivers/overview' },
      { text: 'Porovnanie', link: '/sk/guide/drivers/' },
      { text: 'Redis (ext-redis)', link: '/sk/guide/drivers/redis' },
      { text: 'Predis', link: '/sk/guide/drivers/predis' },
      { text: 'RabbitMQ', link: '/sk/guide/drivers/rabbitmq' },
      { text: 'Amazon SQS', link: '/sk/guide/drivers/sqs' },
      { text: 'FileSystem', link: '/sk/guide/drivers/filesystem' },
      { text: 'Kafka', link: '/sk/guide/drivers/kafka' },
    ],
  },
  {
    text: 'Za behu',
    items: [
      { text: 'Opakovanie', link: '/sk/guide/retry' },
      { text: 'Odložené spracovanie', link: '/sk/guide/delayed' },
      { text: 'Emitovanie po commite', link: '/sk/guide/buffered-emitter' },
      { text: 'Priorita', link: '/sk/guide/priority' },
      { text: 'Ukončenie a limit správ', link: '/sk/guide/shutdown' },
      { text: 'Logovanie', link: '/sk/guide/logging' },
    ],
  },
  {
    text: 'Prevádzka',
    items: [
      { text: 'Testovanie', link: '/sk/guide/testing' },
      { text: 'Nasadenie do produkcie', link: '/sk/guide/deployment' },
      { text: 'Rozširovanie Hermesu', link: '/sk/guide/extending' },
      { text: 'Verzie dokumentácie', link: '/sk/guide/versioning' },
    ],
  },
  {
    text: 'Referencia',
    items: [
      { text: 'Referencia API', link: '/sk/api/reference' },
      { text: 'Časté otázky', link: '/sk/faq' },
      { text: 'Zoznam zmien', link: '/sk/changelog' },
      { text: 'Ako prispieť', link: '/sk/contributing' },
      { text: 'Autori', link: '/sk/authors' },
    ],
  },
]

// Hermes 5.x has a single archived page: its README at the 5.0.0 tag. See guide/versioning.
export const sidebar5x: DocsNavItem[] = [
  {
    text: 'Hermes 5.x (archive)',
    items: [
      { text: 'Hermes 5.x', link: '/5.x/' },
      { text: 'Upgrade to 6.x', link: '/guide/versioning#upgrading-from-5-x' },
      { text: 'Current docs (6.x)', link: '/' },
    ],
  },
]

export const sidebar5xSk: DocsNavItem[] = [
  {
    text: 'Hermes 5.x (archív)',
    items: [
      { text: 'Hermes 5.x', link: '/sk/5.x/' },
      { text: 'Prechod na 6.x', link: '/sk/guide/versioning#prechod-z-5-x' },
      { text: 'Aktuálna dokumentácia (6.x)', link: '/sk/' },
    ],
  },
]
