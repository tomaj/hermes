---
title: "Verzie dokumentácie"
description: "Ktorú verziu Hermesu táto dokumentácia opisuje, kde sú archivované staršie verzie, ako sa pridá ďalšia hlavná verzia a ako sa udržiava slovenský preklad."
---

# Verzie dokumentácie

Tieto stránky opisujú Hermes 6.x, aktuálny kód v `src/`. Sú koreňom stránky (`/hermes/` na GitHub Pages). Staršie hlavné verzie sú archivované v podpriečinku pomenovanom podľa verzie, napríklad [`/sk/5.x/`](/sk/5.x/). Menu verzií v hornej lište medzi nimi prepína a menu jazykov prepína medzi [angličtinou](/guide/versioning) a slovenčinou.

<Diagram name="versioning-1" alt="Stránky 6.x v koreni sa skopírujú do docs/6.x, 7.x sa píše do koreňa a docs/sk zrkadlí koreň aj každý archív" />

## Archív 5.x

Hermes 5.0.0 vyšiel skôr, ako táto stránka existovala, a jeho jedinou dokumentáciou bolo README. [Stránka 5.x](/sk/5.x/) je toto README pri tagu `5.0.0` na jednej stránke, s poznámkou o tom, čo sa zmenilo. Nie je to kópia príručky 6.x: tie stránky opisujú správanie, ktoré 5.x nemá, napríklad potvrdzovanie po handleri. [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md) uvádza všetky rozdiely a [Prechod z 5.x](#prechod-z-5-x) hovorí, čo zmeniť pred aktualizáciou.

## Prechod z 5.x

Vydanie 5.1 na vetve `5.x` ([#156](https://github.com/tomaj/hermes/pull/156), zatiaľ nevydané) označí ako zastarané to, čo 6.0 odstraňuje, a hlási to ako `E_USER_DEPRECATED` bez zmeny správania. Keď vyjde, najprv prejdite na 5.1, opravte hlásenia o zastaraní a potom prejdite na 6.0.

## Pridanie ďalšej hlavnej verzie

Zverejnené URL pre 6.x nech zostanú stabilné:

1. Skopírujte anglický strom do `docs/6.x/` a slovenský do `docs/sk/6.x/` (`index.md`, `guide/`, `examples/`, `frameworks/`, `api/`, `faq.md`, `changelog.md`, `contributing.md`).
2. Pridajte položku `6.x` do menu verzií `navEn` a `navSk` v `docs/site-nav.ts` s odkazom na `/6.x/` a `/sk/6.x/`.
3. Pridajte sidebary pre `/6.x/` a `/sk/6.x/` v `docs/.vitepress/config.mts`, vedľa tých pre `/5.x/`.
4. Nové stránky napíšte do koreňa a do `docs/sk/` a prepnite `currentVersion` v `docs/site-nav.ts`.

`versionLabel()` v `docs/.vitepress/theme/index.ts` ukazuje v menu verziu z cesty, napríklad `/5.x/`, a inde `currentVersion`.

Nevymýšľajte na týchto stránkach API neskoršej verzie. Keď sa kód zmení, aktualizujte stránku, ktorá cituje `src/`, a staré správanie ponechajte v archivovanom strome.

## Slovenský preklad

Predvolený jazyk v koreni stránky je angličtina. Slovenčina je druhý jazyk VitePress pod `/sk/` s rovnakými cestami: `/guide/retry` je `/sk/guide/retry`. Menu jazykov v hornej lište prepojí každú stránku s jej prekladom a každá stránka má odkazy `hreflang` na obe verzie.

- Zmena anglickej stránky potrebuje rovnakú zmenu v `docs/sk/`. `npm run docs:links` zlyhá, keď stránka existuje len v jednom jazyku.
- Ukážky kódu sú v oboch jazykoch rovnaké a načítavajú sa z `examples/`, takže sa nikdy neprekladajú.
- Diagramy sú spoločné. Popisy v SVG zostávajú v angličtine a preložený je text `alt`.
- Texty témy, napríklad popisy ukážky workera, sú v `docs/.vitepress/theme/i18n.ts`.

## Čo neduplikovať

Report pokrytia nie je stránka VitePress. CI workflow nahrá `coverage-report` a `.github/workflows/docs.yml` ho pri nasadení skopíruje do `dist/coverage/`. Archivovaný alebo slovenský strom odkazuje na `https://tomaj.github.io/hermes/coverage/` namiesto vkladania HTML reportu.

## Časté otázky

### Ktorú verziu tieto stránky opisujú?

Hermes 6.x, kód v `src/` na predvolenej vetve.

### Kde je dokumentácia k 5.x?

Na [stránke 5.x](/sk/5.x/), čo je README pri tagu `5.0.0`. Odkazuje na ňu menu verzií v hornej lište.

### Kam pôjde dokumentácia ďalšej hlavnej verzie?

Do koreňa stránky, pričom stránky 6.x sa skopírujú do `docs/6.x/` a `docs/sk/6.x/`, aby ich URL zostali stabilné.

### Existuje slovenský preklad?

Áno, každej stránky, pod [/sk/](/sk/). Použite menu jazykov v hornej lište.

### Je report pokrytia súčasťou zostavenia dokumentácie?

Nie. CI ho nahrá a workflow dokumentácie ho pri nasadení skopíruje do `/coverage/`.

### Ako nahlásiť chybu v dokumentácii?

Použite odkaz na úpravu v spodnej časti ľubovoľnej stránky alebo otvorte issue. Pozrite [Prispievanie](/sk/contributing).
