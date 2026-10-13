<script setup lang="ts">
import { ref, useId } from 'vue'
import Diagram from './Diagram.vue'

interface Tab { label: string, name: string, alt: string }

const props = defineProps<{ tabs: Tab[] }>()
const active = ref(0)
const id = useId()
const buttons = ref<HTMLButtonElement[]>([])

function onKey(event: KeyboardEvent) {
  const count = props.tabs.length
  const next = event.key === 'ArrowRight' ? (active.value + 1) % count
    : event.key === 'ArrowLeft' ? (active.value - 1 + count) % count
      : event.key === 'Home' ? 0
        : event.key === 'End' ? count - 1 : -1
  if (next < 0) return
  event.preventDefault()
  active.value = next
  buttons.value[next]?.focus()
}
</script>

<template>
  <div class="diagram-tabs">
    <div class="diagram-tabs-list" role="tablist" @keydown="onKey">
      <button
        v-for="(tab, i) in tabs"
        :id="`${id}-tab-${i}`"
        :key="tab.name"
        ref="buttons"
        type="button"
        role="tab"
        class="diagram-tabs-tab"
        :aria-selected="active === i"
        :aria-controls="`${id}-panel-${i}`"
        :tabindex="active === i ? 0 : -1"
        @click="active = i"
      >
        {{ tab.label }}
      </button>
    </div>
    <div
      v-for="(tab, i) in tabs"
      v-show="active === i"
      :id="`${id}-panel-${i}`"
      :key="tab.name"
      role="tabpanel"
      :aria-labelledby="`${id}-tab-${i}`"
    >
      <Diagram :name="tab.name" :alt="tab.alt" />
    </div>
  </div>
</template>
