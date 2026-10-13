<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import HeroFlow from './HeroFlow.vue'
</script>

<template>
  <DefaultTheme.Layout>
    <template #home-hero-image>
      <HeroFlow />
    </template>
  </DefaultTheme.Layout>
</template>
