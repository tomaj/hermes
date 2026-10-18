// Runs inside headless Chrome. Turns a hand-placed drawing (docs/diagrams/*.json)
// into hand-drawn Excalidraw elements and exports them as SVG.
import { exportToSvg } from '@excalidraw/utils'

const INK = '#1f2937'
const MUTED = '#6b7280'
const FONT = 5 // Excalifont
const LINE_HEIGHT = 1.25

let ctx
function measure(text, size) {
  if (!ctx) ctx = document.createElement('canvas').getContext('2d')
  ctx.font = `${size}px Excalifont`
  const lines = text.split('\n')
  return { width: Math.max(...lines.map((l) => ctx.measureText(l).width)), height: lines.length * size * LINE_HEIGHT }
}

export async function render(name, source) {
  await document.fonts.load('20px Excalifont')
  const drawing = JSON.parse(source)

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

  const pick = (d) => ({
    strokeColor: d.color === 'muted' ? MUTED : d.color || INK,
    strokeStyle: d.dashed ? 'dashed' : d.dotted ? 'dotted' : 'solid',
    strokeWidth: d.width ?? 2,
    opacity: d.opacity ?? 100,
    ...(d.fill ? { backgroundColor: d.fill, fillStyle: d.hachure ? 'hachure' : 'solid' } : {}),
    ...(d.roughness !== undefined ? { roughness: d.roughness } : {}),
  })
  for (const d of drawing.elements) {
    if (d.t === 'rect' || d.t === 'ellipse' || d.t === 'diamond') {
      base(d.t === 'rect' ? 'rectangle' : d.t, d.x, d.y, d.w, d.h, {
        ...pick(d), roundness: d.t === 'rect' && d.round !== false ? { type: 3 } : null,
      })
    } else if (d.t === 'text') {
      const size = d.size ?? 20
      const m = measure(d.text, size)
      const x = d.align === 'left' ? d.x : d.align === 'right' ? d.x - m.width : d.x - m.width / 2
      text(x, d.y, d.text, size, { ...pick(d), strokeWidth: 1, textAlign: d.align ?? 'center', fontFamily: d.mono ? 3 : FONT })
    } else if (d.t === 'line' || d.t === 'arrow') {
      const pts = d.points.map(([px, py]) => [px - d.points[0][0], py - d.points[0][1]])
      const xs = pts.map((q) => q[0]); const ys = pts.map((q) => q[1])
      base(d.t, d.points[0][0], d.points[0][1], Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys), {
        ...pick(d), points: pts, roundness: d.curve ? { type: 2 } : null,
        lastCommittedPoint: null, startBinding: null, endBinding: null,
        startArrowhead: null, endArrowhead: d.t === 'arrow' ? 'arrow' : null,
      })
    } else {
      throw new Error(`${name}: unsupported drawing element ${d.t}`)
    }
  }
  const padding = drawing.padding ?? 0

  const scene = {
    type: 'excalidraw', version: 2, source: 'https://tomaj.github.io/hermes/',
    elements, appState: { viewBackgroundColor: '#ffffff', gridSize: null }, files: {},
  }
  const svg = async (dark) => {
    const node = await exportToSvg({
      data: { elements, appState: { exportBackground: false, exportWithDarkMode: dark, viewBackgroundColor: '#ffffff' }, files: {} },
      config: { padding },
    })
    node.setAttribute('role', 'img')
    return new XMLSerializer().serializeToString(node)
  }
  return { scene, light: await svg(false), dark: await svg(true) }
}

window.renderDiagram = render
