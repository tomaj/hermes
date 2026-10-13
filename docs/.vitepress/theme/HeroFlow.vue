<script setup lang="ts">
import { withBase } from 'vitepress'
</script>

<template>
  <figure class="hero-sketch" role="img"
    aria-label="A web app on a web server emits a message into a queue and answers the user at once. A background worker, in its own process, takes the message from the queue and does the slow work.">
    <img class="hero-sketch-art hero-sketch-light" :src="withBase('/diagrams/home-hero-light.svg')" alt="" width="700" height="400">
    <img class="hero-sketch-art hero-sketch-dark" :src="withBase('/diagrams/home-hero-dark.svg')" alt="" width="700" height="400">
    <!-- Moving parts. Same 700x400 coordinates as the drawing underneath. -->
    <svg class="hero-sketch-motion" viewBox="0 0 700 400" aria-hidden="true">
      <g class="hs-click">
        <circle cx="110" cy="182" r="12" />
        <circle cx="110" cy="182" r="22" />
      </g>
      <g class="hs-cursor">
        <path d="M0 0 L0 22 L6 16 L11 27 L15 25 L10 14 L18 14 Z" />
      </g>
      <g class="hs-toast">
        <rect x="190" y="166" width="82" height="32" rx="8" />
        <text x="231" y="188">queued ✓</text>
      </g>
      <g class="hs-envelope">
        <rect x="-16" y="-11" width="32" height="22" rx="3" />
        <path d="M-16 -9 L0 3 L16 -9" />
      </g>
      <g class="hs-log">
        <text class="l1" x="512" y="262">← invoice #42</text>
        <text class="l2" x="512" y="286">rendering PDF…</text>
        <text class="l3" x="512" y="310">sending email…</text>
        <text class="l4" x="512" y="334">✓ done in 1.4 s</text>
      </g>
    </svg>
  </figure>
</template>

<style scoped>
.hero-sketch {
  position: relative;
  width: min(100%, 560px);
  margin: 0;
  color: var(--vp-c-text-1);
}
.hero-sketch-art { display: block; width: 100%; height: auto; }

.hero-sketch-motion {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  font-family: 'Excalifont', 'Comic Sans MS', cursive;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.hero-sketch-motion text { stroke: none; fill: currentColor; font-size: 15px; }

.hs-cursor path { fill: var(--vp-c-bg); stroke: currentColor; stroke-width: 1.6; }
.hs-click circle { stroke: var(--vp-c-brand-1); opacity: 0; }
.hs-toast rect { fill: var(--vp-c-bg); stroke: var(--vp-c-brand-1); }
.hs-toast text { fill: var(--vp-c-brand-1); text-anchor: middle; }
.hs-envelope rect { fill: var(--vp-c-bg); }

/* One loop is 9 seconds. */
.hs-cursor { animation: hs-cursor 9s ease-in-out infinite; }
.hs-click circle { animation: hs-click 9s ease-out infinite; transform-origin: 110px 182px; transform-box: view-box; }
.hs-toast { animation: hs-toast 9s ease infinite; }
.hs-envelope { animation: hs-envelope 9s ease-in-out infinite; }
.hs-log text { opacity: 0; animation: 9s ease infinite; }
.hs-log .l1 { animation-name: hs-l1; }
.hs-log .l2 { animation-name: hs-l2; }
.hs-log .l3 { animation-name: hs-l3; }
.hs-log .l4 { animation-name: hs-l4; fill: var(--vp-c-brand-1); }

@keyframes hs-cursor {
  0% { transform: translate(250px, 120px); opacity: 0; }
  4% { opacity: 1; }
  11% { transform: translate(112px, 184px); }
  13% { transform: translate(112px, 188px); }
  15% { transform: translate(112px, 184px); }
  30% { transform: translate(150px, 140px); opacity: 1; }
  36%, 100% { transform: translate(150px, 140px); opacity: 0; }
}
@keyframes hs-click {
  0%, 12% { opacity: 0; transform: scale(0.4); }
  14% { opacity: 1; transform: scale(1); }
  22%, 100% { opacity: 0; transform: scale(1.5); }
}
@keyframes hs-toast {
  0%, 15% { opacity: 0; transform: translateY(6px); }
  18%, 88% { opacity: 1; transform: none; }
  94%, 100% { opacity: 0; }
}
@keyframes hs-envelope {
  0%, 13% { transform: translate(110px, 182px) scale(0.5); opacity: 0; }
  15% { transform: translate(110px, 182px) scale(1); opacity: 1; }
  24% { transform: translate(150px, 245px); }
  30% { transform: translate(150px, 320px); }
  38% { transform: translate(290px, 320px); }
  44%, 52% { transform: translate(380px, 312px) rotate(-6deg); }
  60% { transform: translate(470px, 320px); opacity: 1; }
  66% { transform: translate(585px, 238px) scale(0.8); opacity: 0; }
  100% { transform: translate(585px, 238px); opacity: 0; }
}
@keyframes hs-l1 { 0%, 64% { opacity: 0; } 67%, 92% { opacity: 1; } 97%, 100% { opacity: 0; } }
@keyframes hs-l2 { 0%, 70% { opacity: 0; } 73%, 92% { opacity: 1; } 97%, 100% { opacity: 0; } }
@keyframes hs-l3 { 0%, 76% { opacity: 0; } 79%, 92% { opacity: 1; } 97%, 100% { opacity: 0; } }
@keyframes hs-l4 { 0%, 82% { opacity: 0; } 85%, 92% { opacity: 1; } 97%, 100% { opacity: 0; } }

@media (prefers-reduced-motion: reduce) {
  .hero-sketch-motion * { animation: none !important; }
  .hs-cursor, .hs-click, .hs-envelope { display: none; }
  .hs-log text, .hs-toast { opacity: 1; }
}
</style>
