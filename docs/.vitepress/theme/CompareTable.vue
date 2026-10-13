<script setup lang="ts">
import { computed } from 'vue'

interface Row { label: string, cells: string[] }

const props = defineProps<{ columns: string[], rows: Row[], corner?: string }>()

const marks: Record<string, { cls: string, name: string }> = {
  '✓': { cls: 'yes', name: 'Yes' },
  '✗': { cls: 'no', name: 'No' },
  '~': { cls: 'partly', name: 'Partly' },
}

function split(cell: string) {
  const mark = Object.keys(marks).find((m) => cell.startsWith(m + ' ') || cell === m)
  return mark ? { mark, ...marks[mark], text: cell.slice(mark.length).trim() } : { mark: '', cls: '', name: '', text: cell }
}

const table = computed(() => props.rows.map((row) => ({ label: row.label, cells: row.cells.map(split) })))
const cards = computed(() => props.columns.map((column, i) => ({
  column,
  items: props.rows.map((row) => ({ label: row.label, ...split(row.cells[i] ?? '') })),
})))
</script>

<template>
  <div class="compare">
    <div class="compare-wide">
      <table>
        <thead>
          <tr>
            <th scope="col">{{ corner ?? '' }}</th>
            <th v-for="column in columns" :key="column" scope="col" v-html="column" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in table" :key="row.label">
            <th scope="row">{{ row.label }}</th>
            <td v-for="(cell, i) in row.cells" :key="i">
              <span v-if="cell.mark" class="mark" :class="cell.cls" :title="cell.name" :aria-label="cell.name">{{ cell.mark }}</span>
              <span v-html="cell.text" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="compare-cards">
      <section v-for="card in cards" :key="card.column" class="compare-card">
        <h4 v-html="card.column" />
        <dl>
          <template v-for="item in card.items" :key="item.label">
            <dt>{{ item.label }}</dt>
            <dd>
              <span v-if="item.mark" class="mark" :class="item.cls" :title="item.name" :aria-label="item.name">{{ item.mark }}</span>
              <span v-html="item.text" />
            </dd>
          </template>
        </dl>
      </section>
    </div>
  </div>
</template>
