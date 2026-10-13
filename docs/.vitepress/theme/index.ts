import DefaultTheme from 'vitepress/theme'
import { inBrowser } from 'vitepress'
import Layout from './Layout.vue'
import Diagram from './Diagram.vue'
import WorkerDemo from './WorkerDemo.vue'
import CompareTable from './CompareTable.vue'
import './custom.css'

function versionLabel(path: string): string {
  if (path.includes('/6.x/')) {
    return '6.x'
  }
  return '5.x'
}

function showVersion(path: string): void {
  const label = versionLabel(path)
  document.querySelectorAll('.VPNavBarMenu .text, .VPNavBarExtra .text').forEach((node) => {
    const text = node.textContent?.trim()
    if (text === '5.x' || text === '6.x') {
      node.textContent = label
    }
  })
}

export default {
  extends: DefaultTheme,
  Layout,
  enhanceApp({ app, router }) {
    app.component('Diagram', Diagram)
    app.component('WorkerDemo', WorkerDemo)
    app.component('CompareTable', CompareTable)
    if (!inBrowser) {
      return
    }
    const apply = (path: string) => {
      showVersion(path)
      window.setTimeout(() => showVersion(path), 0)
    }
    router.onAfterRouteChanged = apply
    window.addEventListener('load', () => apply(location.pathname))
  },
}
