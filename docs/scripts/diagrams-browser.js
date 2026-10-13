// Runs inside headless Chrome. Turns Mermaid source into hand-drawn
// Excalidraw elements and exports them as SVG.
import { parseMermaidToExcalidraw } from '@excalidraw/mermaid-to-excalidraw'
import { exportToSvg } from '@excalidraw/utils'

const INK = '#1f2937'
const MUTED = '#6b7280'
const FONT = 5 // Excalifont
const LINE_HEIGHT = 1.25
const LABEL_GAP = 10

let ctx
function measure(text, size) {
  if (!ctx) ctx = document.createElement('canvas').getContext('2d')
  ctx.font = `${size}px Excalifont`
  const lines = text.split('\n')
  return { width: Math.max(...lines.map((l) => ctx.measureText(l).width)), height: lines.length * size * LINE_HEIGHT }
}

function wrap(text, size, max) {
  return text.split('\n').map((para) => {
    const out = []
    let line = ''
    for (const word of para.split(' ')) {
      const next = line ? line + ' ' + word : word
      if (line && measure(next, size).width > max) { out.push(line); line = word } else { line = next }
    }
    out.push(line)
    return out.join('\n')
  }).join('\n')
}

function clean(text) {
  return String(text ?? '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#39;/g, "'")
    .trim()
}

export async function render(name, source) {
  await document.fonts.load('20px Excalifont')
  const { elements: skeleton } = await parseMermaidToExcalidraw(source, {
    themeVariables: { fontSize: '20px' },
    flowchart: { curve: 'basis', nodeSpacing: 55, rankSpacing: 70, padding: 14, wrappingWidth: 160 },
    sequence: { actorMargin: 30, width: 120, messageMargin: 30, boxMargin: 8, noteMargin: 8 },
  })

  let n = 0
  let seed = [...name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 7)
  const elements = []
  const base = (type, x, y, width, height, extra = {}) => {
    n += 1
    seed = (seed * 1103515245 + 12345) >>> 0
    const el = {
      id: `${name}-${n}`, type, x, y, width, height, angle: 0,
      strokeColor: INK, backgroundColor: 'transparent', fillStyle: 'solid',
      strokeWidth: 2, strokeStyle: 'solid', roughness: 1, opacity: 100,
      groupIds: [], frameId: null, roundness: null, seed: seed % 2147483647,
      version: 1, versionNonce: n, isDeleted: false, boundElements: null,
      updated: 1, link: null, locked: false, ...extra,
    }
    elements.push(el)
    return el
  }
  const text = (x, y, value, size, extra = {}) => {
    const m = measure(value, size)
    return base('text', x, y, m.width, m.height, {
      text: value, originalText: value, fontSize: size, fontFamily: FONT,
      textAlign: 'center', verticalAlign: 'middle', containerId: null,
      lineHeight: LINE_HEIGHT, autoResize: true, strokeWidth: 1, ...extra,
    })
  }

  for (const s of skeleton) {
    const stroke = s.strokeStyle === 'dotted' ? 'dashed' : (s.strokeStyle ?? 'solid')
    if (s.type === 'rectangle' && !Number.isFinite(s.width) && s.label?.text) {
      // Tag of an alt or loop block. Pin it to the corner of the block with a
      // paper-coloured fill so the lifeline behind it does not cross the text.
      const value = clean(s.label.text)
      const m = measure(value, 16)
      base('rectangle', s.x, s.y, m.width + 16, m.height + 6, {
        strokeColor: MUTED, strokeWidth: 1, backgroundColor: '#ffffff', fillStyle: 'solid', roundness: { type: 3 },
      })
      text(s.x + 8, s.y + 3, value, 16, { strokeColor: MUTED })
    } else if (s.type === 'rectangle' || s.type === 'diamond' || s.type === 'ellipse') {
      const label = clean(s.label?.text)
      const size = 20
      let { x, y, width, height } = s
      if (!Number.isFinite(width)) width = 0
      if (!Number.isFinite(height)) height = 0
      const inner = s.type === 'diamond' ? Math.max(width * 0.6, 170) : Math.max(width - 24, Math.min(measure(label, size).width + 2, width * (s.strokeStyle === 'dashed' ? 3 : 1.15)))
      const value = label ? wrap(label, size, inner) : ''
      const m = value ? measure(value, size) : { width: 0, height: 0 }
      // Grow boxes that are too small for the hand-drawn font.
      const needW = s.type === 'diamond' ? m.width / 0.6 : m.width + 32
      const needH = s.type === 'diamond' ? m.height / 0.55 : m.height + 24
      if (needW > width) { x -= (needW - width) / 2; width = needW }
      if (needH > height) { y -= (needH - height) / 2; height = needH }
      base(s.type, x, y, width, height, {
        strokeStyle: stroke,
        roundness: s.type === 'rectangle' ? { type: 3 } : s.type === 'diamond' ? { type: 2 } : null,
      })
      if (value) text(x + (width - m.width) / 2, y + (height - m.height) / 2, value, size)
    } else if (s.type === 'text') {
      const value = clean(s.text)
      text(s.x, s.y, value, 18, { strokeColor: MUTED, textAlign: 'left' })
    } else if (s.type === 'line') {
      base('line', s.x, s.y, s.width, s.height, {
        points: s.points, strokeColor: MUTED, strokeWidth: 1, strokeStyle: 'dashed',
        lastCommittedPoint: null, startBinding: null, endBinding: null, startArrowhead: null, endArrowhead: null,
      })
    } else if (s.type === 'arrow') {
      const pts = s.points
      const xs = pts.map((p) => p[0]); const ys = pts.map((p) => p[1])
      base('arrow', s.x, s.y, Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys), {
        points: pts, strokeStyle: stroke, strokeColor: INK, roundness: { type: 2 },
        lastCommittedPoint: null, startBinding: null, endBinding: null,
        startArrowhead: s.startArrowhead ?? null, endArrowhead: s.endArrowhead === undefined ? 'arrow' : s.endArrowhead,
      })
      const label = clean(s.label?.text)
      if (label) {
        const size = 18
        const span = Math.max(...xs) - Math.min(...xs)
        const self = s.start?.id && s.start.id === s.end?.id
        const value = wrap(label, size, self ? 320 : Math.max(260, span + 40))
        const m = measure(value, size)
        // Put the label beside the line, a third of the way along, so it never
        // sits on the stroke and labels of crossing arrows do not collide.
        const seg = []
        let total = 0
        for (let i = 1; i < pts.length; i++) {
          const len = Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1])
          seg.push(len); total += len
        }
        let want = total * (pts.length > 2 || Math.abs(pts.at(-1)[1] - pts[0][1]) > 1 ? 0.4 : 0.5)
        let i = 0
        while (i < seg.length - 1 && want > seg[i]) { want -= seg[i]; i++ }
        const a = pts[i]; const b = pts[i + 1] ?? a
        const f = seg[i] ? want / seg[i] : 0
        const mx = s.x + a[0] + (b[0] - a[0]) * f; const my = s.y + a[1] + (b[1] - a[1]) * f
        const dx = b[0] - a[0]; const dy = b[1] - a[1]
        const horizontal = Math.abs(dx) >= Math.abs(dy) * 1.5
        const vertical = Math.abs(dy) >= Math.abs(dx) * 1.5
        let lx; let ly
        if (horizontal) { lx = mx - m.width / 2; ly = my - m.height - LABEL_GAP }
        else if (vertical) { lx = mx + LABEL_GAP + 4; ly = my - m.height / 2 }
        else if (dx * dy > 0) { lx = mx + LABEL_GAP; ly = my - m.height - LABEL_GAP / 2 }
        else { lx = mx + LABEL_GAP; ly = my + LABEL_GAP / 2 }
        // Move the label to the other side of the line when it would cover a box.
        const hits = (x, y) => elements.some((e) => ['rectangle', 'diamond', 'ellipse'].includes(e.type) &&
          x < e.x + e.width && x + m.width > e.x && y < e.y + e.height && y + m.height > e.y)
        if (hits(lx, ly)) {
          const alt = horizontal ? [lx, my + LABEL_GAP] : [mx - LABEL_GAP - 4 - m.width, ly]
          if (!hits(alt[0], alt[1])) { lx = alt[0]; ly = alt[1] }
        }
        text(lx, ly, value, size, { strokeColor: '#374151', textAlign: horizontal ? 'center' : 'left' })
      }
    } else {
      throw new Error(`${name}: unsupported element type ${s.type}`)
    }
  }

  const scene = {
    type: 'excalidraw', version: 2, source: 'https://tomaj.github.io/hermes/',
    elements, appState: { viewBackgroundColor: '#ffffff', gridSize: null }, files: {},
  }
  const svg = async (dark) => {
    const node = await exportToSvg({
      data: { elements, appState: { exportBackground: false, exportWithDarkMode: dark, viewBackgroundColor: '#ffffff' }, files: {} },
      config: { padding: 16 },
    })
    node.setAttribute('role', 'img')
    return new XMLSerializer().serializeToString(node)
  }
  return { scene, light: await svg(false), dark: await svg(true) }
}

window.renderDiagram = render
