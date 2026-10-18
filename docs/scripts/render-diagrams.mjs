#!/usr/bin/env node
// Renders docs/diagrams/*.json (hand-placed drawings) into hand-drawn Excalidraw scenes and SVGs.
//
//   npm run diagrams          write docs/diagrams/*.excalidraw and docs/public/diagrams/*.svg
//   npm run diagrams:check    fail when the committed files are out of date
//
// Needs Chrome or Chromium. Set CHROME_PATH when it is not in a usual place.
import { build } from 'esbuild'
import { createServer } from 'node:http'
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import puppeteer from 'puppeteer-core'

const docs = join(dirname(fileURLToPath(import.meta.url)), '..')
const srcDir = join(docs, 'diagrams')
const outDir = join(docs, 'public', 'diagrams')
const check = process.argv.includes('--check')
const only = process.argv.slice(2).filter((a) => !a.startsWith('--'))

const chrome = process.env.CHROME_PATH || [
  '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
].find((p) => existsSync(p))
if (!chrome) {
  console.error('Chrome not found. Set CHROME_PATH.')
  process.exit(2)
}

const bundle = await build({
  entryPoints: [join(docs, 'scripts', 'diagrams-browser.js')],
  bundle: true, format: 'esm', write: false, logLevel: 'error', minify: true,
})
const fontFile = join(docs, 'node_modules', '@excalidraw', 'utils', 'dist', 'prod', 'assets', 'Excalifont.ttf')
const page = `<!doctype html><html><head><style>@font-face{font-family:Excalifont;src:url(/Excalifont.ttf)}</style></head>
<body><script>window.EXCALIDRAW_ASSET_PATH = location.origin + '/'</script><script type="module" src="/bundle.js"></script></body></html>`
const server = createServer((req, res) => {
  const path = req.url.split('?')[0]
  if (path === '/') return res.writeHead(200, { 'content-type': 'text/html' }).end(page)
  if (path === '/bundle.js') return res.writeHead(200, { 'content-type': 'text/javascript' }).end(bundle.outputFiles[0].contents)
  if (path.endsWith('.ttf') || path.endsWith('.woff2')) {
    return res.writeHead(200, { 'content-type': 'font/ttf' }).end(readFileSync(fontFile))
  }
  res.writeHead(404).end()
}).listen(0)
await new Promise((r) => server.once('listening', r))

const browser = await puppeteer.launch({ executablePath: chrome, args: ['--no-sandbox', '--font-render-hinting=none'] })
const tab = await browser.newPage()
tab.on('pageerror', (e) => console.error(e.message))
await tab.goto(`http://127.0.0.1:${server.address().port}/`)
await tab.waitForFunction('window.renderDiagram', { timeout: 60000 })

mkdirSync(outDir, { recursive: true })
const names = readdirSync(srcDir).filter((f) => /\.json$/.test(f)).sort()
  .filter((f) => only.length === 0 || only.includes(f.replace(/\.json$/, '')))
const stale = []
for (const file of names) {
  const name = file.replace(/\.json$/, '')
  const source = readFileSync(join(srcDir, file), 'utf8')
  const out = await tab.evaluate((n, s) => window.renderDiagram(n, s), name, source)
  const files = {
    [join(srcDir, `${name}.excalidraw`)]: JSON.stringify(out.scene, null, 2) + '\n',
    [join(outDir, `${name}-light.svg`)]: out.light + '\n',
    [join(outDir, `${name}-dark.svg`)]: out.dark + '\n',
  }
  for (const [file, content] of Object.entries(files)) {
    if (check) {
      if (!existsSync(file) || readFileSync(file, 'utf8') !== content) stale.push(file.slice(docs.length + 1))
    } else {
      writeFileSync(file, content)
    }
  }
  if (!check) console.log(`rendered ${name}`)
}
await browser.close()
server.close()

if (check) {
  if (stale.length) {
    console.error('Diagrams are out of date. Run `npm run diagrams` in docs/ and commit the result:')
    stale.forEach((f) => console.error('  ' + f))
    process.exit(1)
  }
  console.log(`${names.length} diagrams are up to date.`)
}
