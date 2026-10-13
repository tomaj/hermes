<script setup lang="ts">
import WorkerDemo from './WorkerDemo.vue'
</script>

<template>
  <section class="see-it-run">
    <div class="container vp-doc">
    <h2 id="see-it-run" tabindex="-1">See it run <a class="header-anchor" href="#see-it-run" aria-label="Permalink to &quot;See it run&quot;">&#8203;</a></h2>
    <p>
      A user registers. The web app emits a <code>user.registered</code> message and answers in milliseconds.
      The worker, a separate CLI process started with <code>php bin/worker.php</code>, takes the message and sends the welcome email.
      When SMTP times out, <code>RetryTrait</code> sends a copy and the next attempt succeeds.
    </p>
    <WorkerDemo />
    </div>
  </section>
</template>

<style scoped>
.see-it-run {
  padding: 0 24px 48px;
}

.container {
  max-width: 1152px;
  margin: 0 auto;
}

@media (min-width: 640px) {
  .see-it-run { padding: 0 48px 48px; }
}

@media (min-width: 960px) {
  .see-it-run { padding: 0 64px 48px; }
}
</style>
