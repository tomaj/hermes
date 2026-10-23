---
title: "Prioritné fronty"
description: "Posielajte urgentné e-maily pred newslettermi cez prioritné fronty Hermesu na Redise a majte workera len na urgentnú prácu."
---

# Prioritné fronty

**Cieľ:** e-mail na obnovu hesla odíde pred potvrdením platby a potvrdenie pred newsletterom, aj keď čakajú tisíce newsletterov. Jedného workera si navyše môžete nechať voľného len na urgentnú prácu.

<Diagram name="examples-priority-1" alt="Emitter zapisuje do hermes_high 200, hermes_normal 150 a hermes 100. bin/worker.php high číta len 200. bin/worker.php číta high, potom normal, potom low." />

<ProjectSource name="priority" :files="['src/PrintHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Súbory

Projekt spúšťa Redis z vlastného `docker-compose.yml`, ako v [návode na uvítací e-mail](/sk/examples/welcome-email). Tento recept má vlastný `src/Hermes.php`, pretože obe strany musia zaregistrovať rovnaké prioritné fronty.

<<< @/../examples/priority/composer.json{json} [composer.json]

<<< @/../examples/priority/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/priority/public/index.php{php} [public/index.php]

<<< @/../examples/priority/src/PrintHandler.php{php} [src/PrintHandler.php]

<<< @/../examples/priority/bin/worker.php{php} [bin/worker.php]

## Spustenie

Najprv vložte správy a workera spustite až potom, aby všetkých päť správ čakalo v Redise a videli ste poradie:

```bash
composer install
docker compose up -d redis
php public/index.php
php bin/worker.php
```

```text
[09:16:53] email.password_reset {"email":"jane@example.com"}
[09:16:53] email.receipt {"order_id":1042}
[09:16:53] email.newsletter {"subscriber_id":2}
[09:16:53] email.newsletter {"subscriber_id":3}
[09:16:53] email.newsletter {"subscriber_id":1}
```

Teraz spustite workera len na urgentnú prácu:

```bash
php public/index.php
php bin/worker.php high
```

Spracuje obnovu hesla a ostatné štyri nechá v Redise pre bežného workera. `make e2e` skontroluje oba behy.

## Úskalia

- **Vo verzii 5.x registrujte fronty vo vzostupnom poradí.** Worker v 5.x číta fronty v opačnom poradí registrácie, nie podľa čísla. Hermes 6.0 ich zoraďuje podľa čísla, takže na poradí už nezáleží. Driver vždy registruje predvolenú prioritu 100 ako prvú. Tento recept preto používa 100 ako najnižšiu prioritu a v tomto poradí pridáva 150 a 200. Priorita pod 100 by sa čítala pred 100. Pozrite [Priorita](/sk/guide/priority) a issue [#50](https://github.com/tomaj/hermes/issues/50).
- **Emitter a worker potrebujú rovnaké fronty.** Vloženie s prioritou, ktorú driver nezaregistroval, vyhodí `UnknownPriorityException`.
- **V rámci jednej priority nie je poradie.** Tri newslettery vyjdú v náhodnom poradí, pretože množina v Redise žiadne nemá.
- **Opakovania idú na predvolenú prioritu.** Dispatcher pošle kópiu na opakovanie bez priority, takže neúspešná obnova hesla sa zopakuje so 100, za potvrdeniami platieb.
- **Nízka priorita môže čakať navždy.** Kým prichádzajú správy s vysokou prioritou, worker, ktorý číta všetky fronty, sa k nízkym nikdy nedostane. Ak newslettery musia odísť, pridajte workera pre nízku prioritu.
- **Priority majú Redis drivery a `FileSystemDriver`.** Na RabbitMQ, SQS a Kafka `setupPriorityQueue()` vyhodí `NotSupportedException`. Tam použite samostatné fronty a workery.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/priority` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/priority). Naklonujte repozitár, spustite `cd examples/priority` a `make install up`, potom `make e2e`.

## Časté otázky

### Prečo je najnižšia priorita 100?

Vo verzii 5.x driver vždy registruje 100 ako prvú a číta v opačnom poradí registrácie, takže priorita pod 100 by sa spustila pred ňou. Od 6.0 sa fronty čítajú podľa čísla ([#50](https://github.com/tomaj/hermes/issues/50)) a nižšie priority fungujú podľa očakávania.

### Prečo newslettery vychádzajú v náhodnom poradí?

Každá priorita je množina v Redise, ktorá nemá poradie.

### Kam idú opakovania?

Na predvolenú prioritu 100, pretože dispatcher posiela kópie na opakovanie bez priority.

### Ako si nechať workera voľného na urgentnú prácu?

Spustite ho cez `php bin/worker.php high`, ktorý volá `handle([200])`.

### Dokáže to RabbitMQ alebo SQS?

Nie. Tam použite samostatné fronty a samostatné workery.
