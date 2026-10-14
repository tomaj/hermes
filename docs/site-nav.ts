export interface DocsNavItem {
  text: string
  link?: string
  items?: DocsNavItem[]
}

export const currentVersion = '5.x'

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
      { text: '5.x (latest)', link: '/' },
      { text: 'Versioning', link: '/guide/versioning' },
    ],
  },
  { text: 'Coverage', link: coverageLink },
  { text: 'Releases', link: releasesLink },
]

export const sidebar5x: DocsNavItem[] = [
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
    ],
  },
  {
    text: 'Runtime',
    items: [
      { text: 'Retry', link: '/guide/retry' },
      { text: 'Delayed execution', link: '/guide/delayed' },
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
