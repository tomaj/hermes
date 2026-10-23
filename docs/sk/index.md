---
layout: home

hero:
  name: Hermes
  text: Úlohy na pozadí pre PHP
  tagline: Z požiadavky vložte správu a pomalú časť nechajte na CLI workera. Redis, Predis, RabbitMQ, Amazon SQS, Kafka a lokálny adresár vo verzii 6.x.
  image:
    src: /illustrations/hero-light.svg
    alt: Webová aplikácia vkladá správu do fronty, ktorú spracuje worker
  actions:
    - theme: brand
      text: Začíname
      link: /sk/guide/getting-started
    - theme: alt
      text: Na GitHube
      link: https://github.com/tomaj/hermes

features:
  - icon:
      src: /icons/queues.svg
      alt: Fronta
      width: 42px
      height: 42px
    title: Šesť vstavaných driverov
    details: Redis cez ext-redis, Predis, lazy RabbitMQ, Amazon SQS, Kafka a súborový systém. Každý implementuje DriverInterface.
  - icon:
      src: /icons/retry.svg
      alt: Opakovanie
      width: 42px
      height: 42px
    title: Opakovanie pri chybe
    details: Keď handler vyhodí výnimku, RetryTrait naplánuje ďalší pokus. Na Redise, Predise a súborovom systéme sa oneskorenie naozaj dodrží.
  - icon:
      src: /icons/priority.svg
      alt: Priorita
      width: 42px
      height: 42px
    title: Prioritné fronty
    details: Redis a FileSystem drivery majú pre každú prioritu samostatnú frontu a vždy najprv čítajú najvyššiu prioritu.
  - icon:
      src: /icons/shutdown.svg
      alt: Ukončenie
      width: 42px
      height: 42px
    title: Čisté ukončenie
    details: Workera zastavíte cez zdieľaný súbor alebo kľúč v Redise. Znova ho spustí správca procesov.
  - icon:
      src: /icons/extend.svg
      alt: Rozšírenie
      width: 42px
      height: 42px
    title: Malé rozhranie na rozšírenie
    details: Driver tvoria metódy send, wait a setupPriorityQueue. Handler je jedna metóda handle.
  - icon:
      src: /icons/workers.svg
      alt: Workery
      width: 42px
      height: 42px
    title: Veľa workerov, jedna fronta
    details: Ten istý CLI skript spustite vo viacerých procesoch. Redis SPOP aj brokery doručia každú správu len jednému z nich.
---

<div class="home-more">

## Kam ďalej

- [Začíname](/sk/guide/getting-started) nainštaluje 6.x a spustí jedného workera.
- [Príklady](/sk/examples/) sú kompletné recepty. Prvý je [návod na uvítací e-mail](/sk/examples/welcome-email) od prázdneho priečinka.
- [Pojmy](/sk/guide/concepts) vysvetľujú Message, Emitter, Dispatcher, Handler a Driver.
- [Porovnanie driverov](/sk/guide/drivers/) ukazuje prioritu, oneskorenie, perzistenciu a potvrdzovanie vedľa seba.
- [Referencia API](/sk/api/reference) vymenúva triedy, ktoré sú v `src/`.

</div>
