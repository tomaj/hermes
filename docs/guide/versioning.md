---
title: "Versioning docs"
description: "Which Hermes version these docs describe and how 6.x and a Slovak translation will be added."
---

# Versioning docs

These pages describe Hermes 5.x, the current code in `src/`. They are the site root (`/hermes/` on GitHub Pages).

<Diagram name="versioning-1" alt="The 5.x pages at the site root are copied to docs/5.x, 6.x is written at the root, and docs/sk can be added as a locale" />

## Adding 6.x later

Keep the published URLs for 5.x stable:

1. Copy this English tree to `docs/5.x/` (`index.md`, `guide/`, `api/`, `faq.md`, `changelog.md`, `contributing.md`).
2. Point the `5.x` item in `docs/site-nav.ts` at `/5.x/`.
3. Add a `sidebar` entry for `/5.x/` in `docs/.vitepress/config.mts`.
4. Write the 6.x pages at the site root and switch `currentVersion` to `6.x`.

`docs/.vitepress/theme/index.ts` already treats a `/6.x/` path as the 6.x label in the nav. The version menu is the `5.x` dropdown. Add the new item beside `5.x (latest)` and `Versioning`.

Do not invent 6.x APIs on these pages. When the code changes, update the page that cites `src/` and keep the old behavior under `docs/5.x/`.

## Adding Slovak later

English is the only locale. `defineConfig` sets `locales.root` to `en-US`. Add Slovak as a second locale, the way a VitePress site usually does:

1. Mirror the pages under `docs/sk/`.
2. Add a `sk` locale in `docs/.vitepress/config.mts` with `lang: 'sk'` and `link: '/sk/'`.
3. Export `navSk` and a Slovak sidebar from `docs/site-nav.ts`, next to `navEn` and `sidebar5x`.

The theme, the diagram component, and the SVG files are shared. Translated pages can keep the same diagrams. Light and dark SVG variants do not need a Slovak copy.

## What not to duplicate

The coverage report is not a VitePress page. The CI workflow uploads `coverage-report`, and `.github/workflows/docs.yml` copies it to `dist/coverage/` at deploy time. A 6.x or Slovak tree should keep linking to `https://tomaj.github.io/hermes/coverage/` rather than embedding the HTML report.

## FAQ

### Which version do these pages describe?

Hermes 5.x, the code in `src/` on the default branch.

### Where will 6.x documentation go?

At the site root, with the 5.x pages copied to `docs/5.x/` so their URLs stay stable.

### Is there a Slovak translation?

Not yet. English is the only locale. The steps to add `docs/sk/` are on this page.

### Is the coverage report part of the docs build?

No. CI uploads it and the docs workflow copies it to `/coverage/` at deploy time.

### How do I report a mistake in the docs?

Use the edit link at the bottom of any page, or open an issue. See [Contributing](/contributing).
