// Strings that the custom theme components render. Pages carry their own text;
// these are the labels inside components shared by the English and Slovak trees.
import { computed } from 'vue'
import { useData } from 'vitepress'

const en = {
  enlarge: 'Enlarge diagram',
  close: 'Close diagram',
  yes: 'Yes',
  no: 'No',
  partly: 'Partly',
  runnableProject: 'Runnable project',
  frameworkProject: (name: string) => `Every file below is imported from <code>examples/${name}</code>. <code>install.sh</code> copies them into a fresh project, and <code>e2e.sh</code> runs the whole flow once per driver.`,
  recipeProject: (name: string) => `Every file below is imported from <code>examples/${name}</code>, which has a <code>Makefile</code> and an <code>e2e.sh</code> that runs the whole flow. Clone the repository, <code>cd examples/${name}</code>, and run <code>make install up</code>.`,
  keyFiles: 'Key files',
  viewSource: 'View source on GitHub',
  heroAria: 'A web app on a web server emits a message into a queue and answers the user at once. A background worker, in its own process, takes the message from the queue and does the slow work.',
  heroToast: 'queued ✓',
  heroLog: ['← invoice #42', 'rendering PDF…', 'sending email…', '✓ done in 1.4 s'],
  useCasesTitle: 'What people use it for',
  useCasesLead: 'The web request emits a message and answers right away. A worker does the slow part.',
  useCases: {
    email: ['Send emails', 'Signup, password reset, or order mails go out from the worker. The request returns before the SMTP server even answers.'],
    invoice: ['Invoices and PDFs', 'Render the PDF, store it, and attach it to an email in the background. The user clicks once and moves on.'],
    thumbnails: ['Images and video', 'Resize uploads, cut thumbnails, or start a transcode. Heavy CPU work stays off the web servers.'],
    data: ['Data processing', 'Recalculate stats, aggregate reports, or clean up records in small messages that several workers share.'],
    csv: ['Imports and exports', 'Parse a large CSV or build an export file row by row. Tell the user when it is ready instead of making them wait.'],
    webhooks: ['Webhooks', 'Deliver outgoing webhooks from a worker. RetryTrait tries again with a growing delay when the receiver is down.'],
    sync: ['Third-party APIs', 'Sync orders to an ERP, push contacts to a CRM, or call a slow payment API without holding a PHP-FPM process.'],
    notify: ['Notifications', 'Fan out push, SMS, or in-app notifications to many users from one emitted message.'],
    search: ['Search indexing', 'Update Elasticsearch, Meilisearch, or a search table after a save, so the write itself stays fast.'],
    cache: ['Cache warming', 'Rebuild expensive cache entries after a deploy or a content change, before the next visitor asks for them.'],
    reminder: ['Delayed jobs', 'Send a reminder in 24 hours or expire a reservation in 15 minutes with executeAt on Redis or Predis.'],
    jobs: ['Any slow task', 'If it does not have to finish before the response, emit a message and let a handler do it.'],
  } as Record<string, [string, string]>,
  seeItRun: 'See it run',
  seeItRunText: 'A user registers. The web app emits a <code>user.registered</code> message and answers in milliseconds. The worker, a separate CLI process started with <code>php bin/worker.php</code>, takes the message and sends the welcome email. When SMTP times out, <code>RetryTrait</code> sends a copy and the next attempt succeeds.',
  demoAria: 'Left: a user clicks Register on a web page and sees a confirmation in 38 milliseconds. Right: a terminal running php bin/worker.php receives the message, fails once on an SMTP timeout, retries, and finishes.',
  demoTitle: 'Create an account',
  demoButton: 'Register',
  demoOk: '✓ Welcome, Jane! Check your inbox.',
  demoTiming: 'response in 38 ms · the email is sent later',
  demoWebCaption: 'web request',
  demoWorkerCaption: 'background worker',
  demoLines: ['waiting for messages…', 'SendWelcomeEmail: sending…', '✗ SMTP timeout, retry 1 in 15 s', '✓ done, handler returned true'],
  maintainer: 'Creator and maintainer',
  onGitHub: (name: string) => `${name} on GitHub`,
  worksAt: (company: string) => `Works at ${company}`,
  githubSince: (year: string | number) => `On GitHub since ${year}`,
  commitsToHermes: (n: number) => `${n} commits to Hermes`,
  commits: (n: number) => `${n} ${n === 1 ? 'commit' : 'commits'}`,
  you: 'You?',
  howToContribute: 'How to contribute',
  contributingLink: '/contributing',
}

export type Strings = typeof en

const sk: Strings = {
  enlarge: 'Zväčšiť diagram',
  close: 'Zavrieť diagram',
  yes: 'Áno',
  no: 'Nie',
  partly: 'Čiastočne',
  runnableProject: 'Spustiteľný projekt',
  frameworkProject: (name) => `Každý súbor nižšie sa načítava z <code>examples/${name}</code>. <code>install.sh</code> ich skopíruje do nového projektu a <code>e2e.sh</code> spustí celý postup raz pre každý driver.`,
  recipeProject: (name) => `Každý súbor nižšie sa načítava z <code>examples/${name}</code>, kde je <code>Makefile</code> a <code>e2e.sh</code>, ktorý spustí celý postup. Naklonujte repozitár, spustite <code>cd examples/${name}</code> a potom <code>make install up</code>.`,
  keyFiles: 'Hlavné súbory',
  viewSource: 'Zdrojový kód na GitHube',
  heroAria: 'Webová aplikácia na webovom serveri vloží správu do fronty a hneď odpovie používateľovi. Worker na pozadí, vo vlastnom procese, správu z fronty vyberie a urobí pomalú prácu.',
  heroToast: 'vo fronte ✓',
  heroLog: ['← faktúra #42', 'generujem PDF…', 'posielam e-mail…', '✓ hotovo za 1,4 s'],
  useCasesTitle: 'Na čo sa používa',
  useCasesLead: 'Webová požiadavka vloží správu a hneď odpovie. Pomalú časť urobí worker.',
  useCases: {
    email: ['Posielanie e-mailov', 'E-maily po registrácii, pri obnove hesla alebo k objednávke odchádzajú z workera. Požiadavka skončí skôr, než SMTP server vôbec odpovie.'],
    invoice: ['Faktúry a PDF', 'Vygenerujte PDF, uložte ho a priložte k e-mailu na pozadí. Používateľ klikne raz a pokračuje.'],
    thumbnails: ['Obrázky a video', 'Zmenšite nahrané obrázky, vyrežte náhľady alebo spustite prevod videa. Náročná práca pre CPU zostane mimo webových serverov.'],
    data: ['Spracovanie dát', 'Prepočítajte štatistiky, zostavte reporty alebo vyčistite záznamy v malých správach, ktoré si rozdelí viac workerov.'],
    csv: ['Importy a exporty', 'Spracujte veľké CSV alebo zostavte exportný súbor riadok po riadku. Dajte používateľovi vedieť, keď je hotovo, namiesto toho, aby čakal.'],
    webhooks: ['Webhooky', 'Odchádzajúce webhooky doručuje worker. Keď príjemca nefunguje, RetryTrait to skúsi znova s rastúcim odstupom.'],
    sync: ['API tretích strán', 'Synchronizujte objednávky do ERP, posielajte kontakty do CRM alebo volajte pomalé platobné API bez blokovania PHP-FPM procesu.'],
    notify: ['Notifikácie', 'Z jednej správy rozošlite push, SMS alebo notifikácie v aplikácii mnohým používateľom.'],
    search: ['Indexovanie vyhľadávania', 'Aktualizujte Elasticsearch, Meilisearch alebo vyhľadávaciu tabuľku po uložení, aby samotný zápis zostal rýchly.'],
    cache: ['Zahrievanie cache', 'Po nasadení alebo zmene obsahu znova zostavte drahé položky cache skôr, než o ne požiada ďalší návštevník.'],
    reminder: ['Odložené úlohy', 'Pošlite pripomienku o 24 hodín alebo zrušte rezerváciu o 15 minút pomocou executeAt na Redise alebo Predise.'],
    jobs: ['Akákoľvek pomalá úloha', 'Ak nemusí skončiť pred odpoveďou, vložte správu a nechajte ju spracovať handler.'],
  },
  seeItRun: 'Ukážka v praxi',
  seeItRunText: 'Používateľ sa zaregistruje. Webová aplikácia vloží správu <code>user.registered</code> a odpovie za pár milisekúnd. Worker, samostatný CLI proces spustený príkazom <code>php bin/worker.php</code>, správu vyberie a pošle uvítací e-mail. Keď SMTP nestihne odpovedať, <code>RetryTrait</code> pošle kópiu správy a ďalší pokus uspeje.',
  demoAria: 'Vľavo: používateľ klikne na webovej stránke na Registrovať a do 38 milisekúnd vidí potvrdenie. Vpravo: terminál s php bin/worker.php prijme správu, raz zlyhá na timeoute SMTP, skúsi to znova a dokončí ju.',
  demoTitle: 'Vytvoriť účet',
  demoButton: 'Registrovať',
  demoOk: '✓ Vitajte, Jana! Pozrite si schránku.',
  demoTiming: 'odpoveď za 38 ms · e-mail odíde neskôr',
  demoWebCaption: 'webová požiadavka',
  demoWorkerCaption: 'worker na pozadí',
  demoLines: ['čakám na správy…', 'SendWelcomeEmail: posielam…', '✗ timeout SMTP, pokus 1 o 15 s', '✓ hotovo, handler vrátil true'],
  maintainer: 'Autor a správca',
  onGitHub: (name) => `${name} na GitHube`,
  worksAt: (company) => `Pracuje v ${company}`,
  githubSince: (year) => `Na GitHube od roku ${year}`,
  commitsToHermes: (n) => `${n} commitov do Hermesu`,
  commits: (n) => `${n} ${n === 1 ? 'commit' : n >= 2 && n <= 4 ? 'commity' : 'commitov'}`,
  you: 'Vy?',
  howToContribute: 'Ako prispieť',
  contributingLink: '/sk/contributing',
}

export function stringsFor(lang: string): Strings {
  return lang.startsWith('sk') ? sk : en
}

export function useStrings() {
  const { lang } = useData()
  return computed(() => stringsFor(lang.value))
}
