<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import HeroFlow from './HeroFlow.vue'
import UseCases from './UseCases.vue'
import SeeItRun from './SeeItRun.vue'
</script>

<template>
  <DefaultTheme.Layout>
    <template #home-hero-image>
      <HeroFlow />
    </template>
    <template #home-features-before>
      <UseCases />
      <SeeItRun />
    </template>
  </DefaultTheme.Layout>
</template>
