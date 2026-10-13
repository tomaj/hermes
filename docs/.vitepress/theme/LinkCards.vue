<script setup lang="ts">
import { withBase } from 'vitepress'

defineProps<{ items: { title: string, text: string, link: string }[] }>()
</script>

<template>
  <ul class="link-cards">
    <li v-for="item in items" :key="item.link">
      <a class="link-card" :href="withBase(item.link)">
        <span class="link-card-title">{{ item.title }}</span>
        <span class="link-card-text">{{ item.text }}</span>
      </a>
    </li>
  </ul>
</template>
