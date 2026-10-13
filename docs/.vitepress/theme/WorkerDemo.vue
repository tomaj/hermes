<template>
  <div class="worker-demo" role="img"
    aria-label="Left: a user clicks Register on a web page and sees a confirmation in 38 milliseconds. Right: a terminal running php bin/worker.php receives the message, fails once on an SMTP timeout, retries, and finishes.">
    <div class="pane web" aria-hidden="true">
      <div class="bar"><i /><i /><i /><span class="url">app.example.com/register</span></div>
      <div class="page">
        <h4>Create an account</h4>
        <div class="field">jane@example.com</div>
        <div class="field dots">••••••••</div>
        <div class="button">
          <span class="label">Register</span>
          <span class="spinner" />
        </div>
        <div class="ok">✓ Welcome, Jane! Check your inbox.</div>
        <div class="timing">response in 38 ms · the email is sent later</div>
        <svg class="cursor" viewBox="0 0 20 28"><path d="M1 1 L1 23 L7 17 L12 27 L16 25 L11 15 L19 15 Z" /></svg>
      </div>
      <p class="caption">web request</p>
    </div>
    <div class="pane term" aria-hidden="true">
      <div class="bar"><i /><i /><i /><span class="url">worker</span></div>
      <pre class="screen"><span class="ln l0"><b>$</b> php bin/worker.php</span>
<span class="ln l1"><em>[09:41:02]</em> waiting for messages…</span>
<span class="ln l2"><em>[09:41:05]</em> <b>←</b> user.registered  id=1f3a</span>
<span class="ln l3"><em>[09:41:05]</em> SendWelcomeEmail: sending…</span>
<span class="ln l4 bad"><em>[09:41:06]</em> ✗ SMTP timeout, retry 1 in 15 s</span>
<span class="ln l5"><em>[09:41:21]</em> <b>←</b> user.registered  id=1f3a  retry=1</span>
<span class="ln l6"><em>[09:41:21]</em> SendWelcomeEmail: sending…</span>
<span class="ln l7 good"><em>[09:41:22]</em> ✓ done, handler returned true</span>
<span class="ln l8"><em>[09:41:22]</em> waiting for messages…<span class="caret">▍</span></span></pre>
      <p class="caption">background worker</p>
    </div>
  </div>
</template>

<style scoped>
.worker-demo {
  display: grid;
  grid-template-columns: 1fr 1.25fr;
  gap: 20px;
  margin: 20px 0 8px;
  --loop: 12s;
}
@media (max-width: 760px) { .worker-demo { grid-template-columns: 1fr; } }

.pane { position: relative; min-width: 0; }
.bar {
  display: flex; align-items: center; gap: 6px;
  height: 30px; padding: 0 10px;
  border: 2px solid var(--vp-c-text-1); border-bottom-width: 1.5px;
  border-radius: 14px 14px 0 0;
}
.bar i { width: 9px; height: 9px; border: 1.5px solid currentColor; border-radius: 50%; }
.url {
  margin-left: 8px; padding: 0 10px; font-size: 12px; line-height: 18px;
  border: 1px solid var(--vp-c-divider); border-radius: 6px; color: var(--vp-c-text-2);
  font-family: 'Excalifont', var(--vp-font-family-base);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.page, .screen {
  border: 2px solid var(--vp-c-text-1); border-top: 0;
  border-radius: 0 0 14px 14px;
  height: 260px; margin: 0;
}
.caption {
  margin: 8px 0 0 !important; text-align: center;
  font-family: 'Excalifont', var(--vp-font-family-base); color: var(--vp-c-text-2);
}

/* Web page */
.page { position: relative; padding: 18px 22px; font-family: 'Excalifont', var(--vp-font-family-base); }
.page h4 { margin: 0 0 12px; font-size: 20px; font-weight: 400; }
.field {
  border: 1.5px solid var(--vp-c-text-2); border-radius: 8px;
  padding: 4px 10px; margin-bottom: 10px; font-size: 14px; color: var(--vp-c-text-2);
}
.button {
  position: relative; display: inline-flex; align-items: center; justify-content: center;
  min-width: 120px; height: 36px; margin-top: 4px;
  border: 2px solid var(--vp-c-text-1); border-radius: 10px; font-size: 17px;
  animation: wd-press var(--loop) infinite;
}
.spinner {
  position: absolute; width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid currentColor; border-right-color: transparent; opacity: 0;
  animation: wd-spin .6s linear infinite, wd-spinner var(--loop) infinite;
}
.label { animation: wd-label var(--loop) infinite; }
.ok { margin-top: 12px; color: var(--vp-c-brand-1); font-size: 17px; opacity: 0; animation: wd-ok var(--loop) infinite; }
.timing { margin-top: 2px; font-size: 13px; color: var(--vp-c-text-2); opacity: 0; animation: wd-ok var(--loop) infinite; }
.cursor {
  position: absolute; left: 0; top: 0; width: 18px; height: 25px;
  fill: var(--vp-c-bg); stroke: var(--vp-c-text-1); stroke-width: 1.6; stroke-linejoin: round;
  animation: wd-cursor var(--loop) ease-in-out infinite;
}

/* Terminal: dark in both themes */
.term .bar { background: #1f2937; color: #e5e7eb; border-color: var(--vp-c-text-1); }
.term .url { color: #9ca3af; border-color: #4b5563; }
.screen {
  background: #111827; color: #e5e7eb;
  padding: 12px 14px; overflow: hidden;
  font: 13px/1.75 var(--vp-font-family-mono);
  white-space: pre;
}
.screen em { font-style: normal; color: #6b7280; }
.screen b { font-weight: 600; color: #a5b4fc; }
.screen .bad { color: #fca5a5; }
.screen .good { color: #86efac; }
.ln { opacity: 0; animation: var(--loop) infinite; }
.l0 { animation-name: wd-l0; } .l1 { animation-name: wd-l1; } .l2 { animation-name: wd-l2; }
.l3 { animation-name: wd-l3; } .l4 { animation-name: wd-l4; } .l5 { animation-name: wd-l5; }
.l6 { animation-name: wd-l6; } .l7 { animation-name: wd-l7; } .l8 { animation-name: wd-l8; }
.caret { animation: wd-blink 1s steps(1) infinite; }

/* Timeline: click at 14%, response at 17%, worker lines from 20% to 72%, fade at 95%. */
@keyframes wd-cursor {
  0% { transform: translate(230px, 220px); opacity: 0; }
  5% { opacity: 1; }
  12% { transform: translate(84px, 150px); }
  14% { transform: translate(84px, 153px); }
  16% { transform: translate(84px, 150px); }
  30% { transform: translate(250px, 120px); opacity: 1; }
  36%, 100% { transform: translate(250px, 120px); opacity: 0; }
}
@keyframes wd-press { 0%, 13% { transform: none; } 14% { transform: scale(0.95); } 16%, 100% { transform: none; } }
@keyframes wd-label { 0%, 14% { opacity: 1; } 14.5%, 16.5% { opacity: 0; } 17%, 100% { opacity: 1; } }
@keyframes wd-spinner { 0%, 14% { opacity: 0; } 14.5%, 16.5% { opacity: 1; } 17%, 100% { opacity: 0; } }
@keyframes wd-ok { 0%, 16.5% { opacity: 0; transform: translateY(4px); } 18%, 92% { opacity: 1; transform: none; } 97%, 100% { opacity: 0; } }
@keyframes wd-l0 { 0%, 100% { opacity: 1; } }
@keyframes wd-l1 { 0%, 2% { opacity: 0; } 3%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l2 { 0%, 19% { opacity: 0; } 20%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l3 { 0%, 25% { opacity: 0; } 26%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l4 { 0%, 34% { opacity: 0; } 35%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l5 { 0%, 52% { opacity: 0; } 53%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l6 { 0%, 58% { opacity: 0; } 59%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l7 { 0%, 68% { opacity: 0; } 69%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-l8 { 0%, 73% { opacity: 0; } 74%, 95% { opacity: 1; } 99%, 100% { opacity: 0; } }
@keyframes wd-spin { to { transform: rotate(360deg); } }
@keyframes wd-blink { 50% { opacity: 0; } }

@media (prefers-reduced-motion: reduce) {
  .worker-demo * { animation: none !important; }
  .ln, .ok, .timing { opacity: 1; }
  .cursor, .spinner { display: none; }
}
</style>
