---
title: "Versioning docs"
description: "Which Hermes version these docs describe, where older versions are archived, how the next major version is added, and how the Slovak translation is kept in step."
---

# Versioning docs

These pages describe Hermes 6.x, the current code in `src/`. They are the site root (`/hermes/` on GitHub Pages). Older major versions are archived in a subfolder named after the version, such as [`/5.x/`](/5.x/). The version menu in the top bar switches between them, and the language menu switches between English and [Slovak](/sk/guide/versioning).

<Diagram name="versioning-1" alt="The 6.x pages at the site root are copied to docs/6.x, 7.x is written at the root, and docs/sk mirrors the root and each archive" />

## The 5.x archive

Hermes 5.0.0 was released before this site existed, and its only documentation was the README. The [5.x page](/5.x/) is that README at the `5.0.0` tag, on one page, with a note about what changed. It is not a copy of the 6.x guide: those pages describe behaviour that 5.x does not have, such as acknowledging after the handler. [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md) lists every difference, and [Upgrading from 5.x](#upgrading-from-5-x) says what to change before you upgrade.

## Upgrading from 5.x

A 5.1 release on the `5.x` branch ([#156](https://github.com/tomaj/hermes/pull/156), not released yet) deprecates what 6.0 removes and reports it as `E_USER_DEPRECATED`, without changing behaviour. Once it is out, upgrade to 5.1 first, fix the deprecation notices, then move to 6.0.

## Adding the next major version

Keep the published URLs for 6.x stable:

1. Copy the English tree to `docs/6.x/` and the Slovak tree to `docs/sk/6.x/` (`index.md`, `guide/`, `examples/`, `frameworks/`, `api/`, `faq.md`, `changelog.md`, `contributing.md`).
2. Add a `6.x` item to the version menus `navEn` and `navSk` in `docs/site-nav.ts`, pointing at `/6.x/` and `/sk/6.x/`.
3. Add sidebars for `/6.x/` and `/sk/6.x/` in `docs/.vitepress/config.mts`, next to the `/5.x/` ones.
4. Write the new pages at the site root and in `docs/sk/`, and switch `currentVersion` in `docs/site-nav.ts`.

`versionLabel()` in `docs/.vitepress/theme/index.ts` shows the version from a path such as `/5.x/` in the menu, and `currentVersion` everywhere else.

Do not invent APIs of a later version on these pages. When the code changes, update the page that cites `src/`, and keep the old behaviour in the archived tree.

## Slovak translation

English is the default locale at the site root. Slovak is a second VitePress locale under `/sk/`, with the same paths: `/guide/retry` is `/sk/guide/retry`. The language menu in the top bar links each page to its translation, and every page carries `hreflang` links to both.

- A change to an English page needs the same change in `docs/sk/`. `npm run docs:links` fails when a page exists in only one language.
- Code blocks are the same in both languages and are imported from `examples/`, so they are never translated.
- Diagrams are shared. The SVG labels stay in English, and the `alt` text is translated.
- Theme strings, such as the labels of the worker demo, are in `docs/.vitepress/theme/i18n.ts`.

## What not to duplicate

The coverage report is not a VitePress page. The CI workflow uploads `coverage-report`, and `.github/workflows/docs.yml` copies it to `dist/coverage/` at deploy time. An archived or Slovak tree links to `https://tomaj.github.io/hermes/coverage/` rather than embedding the HTML report.

## FAQ

### Which version do these pages describe?

Hermes 6.x, the code in `src/` on the default branch.

### Where is the 5.x documentation?

On the [5.x page](/5.x/), which is the README at the `5.0.0` tag. The version menu in the top bar links to it.

### Where will the next major version's documentation go?

At the site root, with the 6.x pages copied to `docs/6.x/` and `docs/sk/6.x/` so their URLs stay stable.

### Is there a Slovak translation?

Yes, of every page, under [/sk/](/sk/). Use the language menu in the top bar.

### Is the coverage report part of the docs build?

No. CI uploads it and the docs workflow copies it to `/coverage/` at deploy time.

### How do I report a mistake in the docs?

Use the edit link at the bottom of any page, or open an issue. See [Contributing](/contributing).
