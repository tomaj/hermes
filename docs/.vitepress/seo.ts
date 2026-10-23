// Per-page SEO: canonical URL, Open Graph and Twitter tags, and JSON-LD.
// Used by transformHead in config.mts.
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import type { HeadConfig, PageData } from 'vitepress'
import authors from '../data/authors.json'

export const siteUrl = 'https://tomaj.github.io/hermes/'
export const ogImage = `${siteUrl}og.png`
const siteName = 'Hermes'
const repoUrl = 'https://github.com/tomaj/hermes'

const m = authors.maintainer
export const person = {
  '@type': 'Person',
  '@id': `${siteUrl}authors#tomaj`,
  name: m.name,
  url: m.url,
  image: m.avatar,
  sameAs: [m.url, m.blog, m.twitter ? `https://x.com/${m.twitter}` : null].filter(Boolean),
}

export function pageUrl(relativePath: string): string {
  const path = relativePath.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '.html')
  return siteUrl + path
}

// English pages live at the site root, Slovak pages mirror them under sk/.
export function localeOf(relativePath: string): { prefix: string, lang: string, ogLocale: string } {
  return relativePath.startsWith('sk/')
    ? { prefix: 'sk/', lang: 'sk', ogLocale: 'sk_SK' }
    : { prefix: '', lang: 'en-US', ogLocale: 'en_US' }
}

// Section names for the breadcrumb, from the first path segment after the locale.
const sections: Record<string, Record<string, [string, string]>> = {
  '': {
    guide: ['Guide', 'guide/getting-started.html'],
    examples: ['Examples', 'examples/'],
    frameworks: ['Framework integration', 'frameworks/'],
    api: ['Reference', 'api/reference.html'],
    '5.x': ['Hermes 5.x', '5.x/'],
    drivers: ['Drivers', 'guide/drivers/'],
  },
  'sk/': {
    guide: ['Príručka', 'guide/getting-started.html'],
    examples: ['Príklady', 'examples/'],
    frameworks: ['Integrácia do frameworkov', 'frameworks/'],
    api: ['Referencia', 'api/reference.html'],
    '5.x': ['Hermes 5.x', '5.x/'],
    drivers: ['Drivery', 'guide/drivers/'],
  },
}

// The same page in the other locale, when it exists, for hreflang links.
export function alternates(relativePath: string, srcDir: string): { lang: string, href: string }[] {
  const { prefix } = localeOf(relativePath)
  const base = relativePath.slice(prefix.length)
  const pairs: [string, string][] = [['en', base], ['sk', `sk/${base}`]]
  const found = pairs.filter(([, path]) => existsSync(resolve(srcDir, path)))
  if (found.length < 2) return []
  return [
    ...found.map(([lang, path]) => ({ lang, href: pageUrl(path) })),
    { lang: 'x-default', href: pageUrl(base) },
  ]
}

// Same slugs as VitePress: accents dropped, other characters collapsed to dashes.
export function slug(text: string): string {
  return text.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

function breadcrumb(page: PageData, url: string) {
  const { prefix } = localeOf(page.relativePath)
  const home = siteUrl + prefix
  const names = sections[prefix]
  const path = page.relativePath.slice(prefix.length)
  const items: { name: string, item: string }[] = [{ name: siteName, item: home }]
  const section = names[path.split('/')[0]]
  if (section && home + section[1] !== url) items.push({ name: section[0], item: home + section[1] })
  if (path.startsWith('guide/drivers/') && !path.endsWith('index.md')) {
    items.push({ name: names.drivers[0], item: home + names.drivers[1] })
  }
  if (url !== home) items.push({ name: page.title, item: url })
  return {
    '@type': 'BreadcrumbList',
    itemListElement: items.map((entry, i) => ({ '@type': 'ListItem', position: i + 1, name: entry.name, item: entry.item })),
  }
}

// Markdown to plain text, enough for JSON-LD answers.
export function plain(markdown: string): string {
  return markdown
    .replace(/```[\s\S]*?```/g, '')
    .replace(/^<<< .*$/gm, '')
    .replace(/<[^>]+>/g, '')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/[`*_]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

// Questions are the `###` headings under `## FAQ` (`## Časté otázky` in Slovak),
// or every `##` heading on faq.md.
export function faqEntries(source: string, relativePath: string): { q: string, a: string }[] {
  let section: string
  let level: string
  if (relativePath === 'faq.md' || relativePath === 'sk/faq.md') {
    section = source
    level = '## '
  } else {
    const heading = ['\n## FAQ\n', '\n## Časté otázky\n'].find((h) => source.includes(h))
    if (heading === undefined) return []
    section = source.slice(source.indexOf(heading) + heading.length)
    const next = section.search(/\n## /)
    if (next >= 0) section = section.slice(0, next)
    level = '### '
  }
  const parts = ('\n' + section).split('\n' + level).slice(1)
  return parts
    .map((part) => {
      const [q, ...rest] = part.split('\n')
      const body = rest.join('\n').split(/\n#{2,3} /)[0]
      return { q: plain(q), a: plain(body) }
    })
    .filter((e) => e.q && e.a)
}

// Numbered `## 1. Title` headings become HowTo steps.
function howToSteps(source: string, url: string) {
  const steps: { name: string, text: string, url: string }[] = []
  const re = /\n## (\d+)\. ([^\n]+)\n([\s\S]*?)(?=\n## |$)/g
  let match: RegExpExecArray | null
  while ((match = re.exec(source)) !== null) {
    const text = plain(match[3]).slice(0, 300)
    steps.push({ name: match[2].trim(), text: text || match[2].trim(), url: `${url}#_${match[1]}-${slug(match[2])}` })
  }
  return steps
}

export function seoHead(page: PageData, srcDir: string, siteDescription: string): HeadConfig[] {
  if (page.isNotFound || !page.relativePath) return []
  const url = pageUrl(page.relativePath)
  const locale = localeOf(page.relativePath)
  const sk = locale.prefix === 'sk/'
  const isHome = page.frontmatter.layout === 'home'
  const homeTitle = sk ? 'Hermes - úlohy na pozadí pre PHP' : 'Hermes - background jobs for PHP'
  const title = isHome ? homeTitle : `${page.title} | ${siteName}`
  const description = page.description || page.frontmatter.description || siteDescription
  const source = readFileSync(resolve(srcDir, page.relativePath), 'utf8')
  const modified = page.lastUpdated ? new Date(page.lastUpdated).toISOString() : undefined

  const head: HeadConfig[] = [
    ['link', { rel: 'canonical', href: url }],
    ['meta', { property: 'og:type', content: isHome ? 'website' : 'article' }],
    ['meta', { property: 'og:site_name', content: siteName }],
    ['meta', { property: 'og:locale', content: locale.ogLocale }],
    ['meta', { property: 'og:locale:alternate', content: sk ? 'en_US' : 'sk_SK' }],
    ['meta', { property: 'og:title', content: title }],
    ['meta', { property: 'og:description', content: description }],
    ['meta', { property: 'og:url', content: url }],
    ['meta', { property: 'og:image', content: ogImage }],
    ['meta', { property: 'og:image:width', content: '1200' }],
    ['meta', { property: 'og:image:height', content: '630' }],
    ['meta', { property: 'og:image:alt', content: 'Hermes, background jobs for PHP: web app, queue, worker' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:title', content: title }],
    ['meta', { name: 'twitter:description', content: description }],
    ['meta', { name: 'twitter:image', content: ogImage }],
    ['meta', { name: 'author', content: m.name }],
  ]
  for (const alt of alternates(page.relativePath, srcDir)) {
    head.push(['link', { rel: 'alternate', hreflang: alt.lang, href: alt.href }])
  }
  if (modified && !isHome) head.push(['meta', { property: 'article:modified_time', content: modified }])

  const website = { '@type': 'WebSite', '@id': `${siteUrl}#website`, name: siteName, url: siteUrl, inLanguage: ['en-US', 'sk'], publisher: { '@id': person['@id'] } }
  const graph: Record<string, unknown>[] = [person, website]

  if (isHome) {
    graph.push({
      '@type': ['SoftwareSourceCode', 'SoftwareApplication'],
      '@id': `${siteUrl}#software`,
      name: 'Hermes',
      description: siteDescription,
      url: siteUrl,
      codeRepository: repoUrl,
      programmingLanguage: { '@type': 'ComputerLanguage', name: 'PHP' },
      runtimePlatform: 'PHP 8.4',
      applicationCategory: 'DeveloperApplication',
      operatingSystem: 'Linux, macOS, Windows',
      license: 'https://opensource.org/licenses/MIT',
      softwareVersion: '5.x',
      author: { '@id': person['@id'] },
      maintainer: { '@id': person['@id'] },
      image: ogImage,
      offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
    })
  } else {
    graph.push({
      '@type': 'TechArticle',
      '@id': `${url}#article`,
      headline: page.title,
      description,
      url,
      mainEntityOfPage: url,
      inLanguage: locale.lang,
      image: ogImage,
      author: { '@id': person['@id'] },
      publisher: { '@id': person['@id'] },
      isPartOf: { '@id': `${siteUrl}#website` },
      about: { '@type': 'SoftwareSourceCode', name: 'Hermes', codeRepository: repoUrl },
      proficiencyLevel: page.relativePath.slice(locale.prefix.length).startsWith('examples/') ? 'Beginner' : 'Expert',
      ...(modified ? { dateModified: modified } : {}),
    })
    graph.push(breadcrumb(page, url))
  }

  const faq = faqEntries(source, page.relativePath)
  if (faq.length > 0) {
    graph.push({
      '@type': 'FAQPage',
      '@id': `${url}#faq`,
      url,
      mainEntity: faq.map((e) => ({ '@type': 'Question', name: e.q, acceptedAnswer: { '@type': 'Answer', text: e.a } })),
    })
  }

  const steps = page.frontmatter.howto ? howToSteps(source, url) : []
  if (steps.length > 0) {
    graph.push({
      '@type': 'HowTo',
      '@id': `${url}#howto`,
      name: page.title,
      description,
      totalTime: page.frontmatter.howto.totalTime,
      tool: (page.frontmatter.howto.tools ?? []).map((name: string) => ({ '@type': 'HowToTool', name })),
      step: steps.map((s, i) => ({ '@type': 'HowToStep', position: i + 1, name: s.name, text: s.text, url: s.url })),
    })
  }

  if (page.relativePath.slice(locale.prefix.length) === 'authors.md') {
    graph.push({ '@type': 'ProfilePage', '@id': `${url}#profile`, url, mainEntity: { '@id': person['@id'] } })
  }

  head.push(['script', { type: 'application/ld+json' }, JSON.stringify({ '@context': 'https://schema.org', '@graph': graph })])
  return head
}
