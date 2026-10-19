---
title: "Versioning docs"
description: "Which Hermes version these docs describe and how a later version and a Slovak translation will be added."
---

# Versioning docs

These pages describe Hermes 6.x, the current code in `src/`. They are the site root (`/hermes/` on GitHub Pages).

There is no separate 5.x site. Hermes 5.0.0 was released before this site existed; its documentation is the [README at the `5.0.0` tag](https://github.com/tomaj/hermes/blob/5.0.0/README.md). [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md) lists every difference from 5.x.

<Diagram name="versioning-1" alt="The 6.x pages at the site root are copied to docs/6.x, 7.x is written at the root, and docs/sk can be added as a locale" />

## Adding the next major version

Keep the published URLs for 6.x stable:

1. Copy this English tree to `docs/6.x/` (`index.md`, `guide/`, `api/`, `faq.md`, `changelog.md`, `contributing.md`).
2. Add a `6.x` item in `docs/site-nav.ts` that points at `/6.x/`.
3. Add a `sidebar` entry for `/6.x/` in `docs/.vitepress/config.mts`.
4. Write the new pages at the site root and switch `currentVersion` in `docs/site-nav.ts`.

`versionLabel()` in `docs/.vitepress/theme/index.ts` shows the version from a path such as `/6.x/` in the nav, and `currentVersion` everywhere else. The version menu is the `6.x` dropdown, with `6.x (latest)` and `Versioning`.

Do not invent APIs of a later version on these pages. When the code changes, update the page that cites `src/`, and keep the old behavior in the archived tree.

## Adding Slovak later

English is the only locale. `defineConfig` sets `locales.root` to `en-US`. Add Slovak as a second locale, the way a VitePress site usually does:

1. Mirror the pages under `docs/sk/`.
2. Add a `sk` locale in `docs/.vitepress/config.mts` with `lang: 'sk'` and `link: '/sk/'`.
3. Export `navSk` and a Slovak sidebar from `docs/site-nav.ts`, next to `navEn` and `sidebarEn`.

The theme, the diagram component, and the SVG files are shared. Translated pages can keep the same diagrams. Light and dark SVG variants do not need a Slovak copy.

## What not to duplicate

The coverage report is not a VitePress page. The CI workflow uploads `coverage-report`, and `.github/workflows/docs.yml` copies it to `dist/coverage/` at deploy time. An archived or Slovak tree should keep linking to `https://tomaj.github.io/hermes/coverage/` rather than embedding the HTML report.

## FAQ

### Which version do these pages describe?

Hermes 6.x, the code in `src/` on the default branch.

### Where will the next major version's documentation go?

At the site root, with the 6.x pages copied to `docs/6.x/` so their URLs stay stable. 5.x has no pages of its own: see the README at the `5.0.0` tag.

### Is there a Slovak translation?

Not yet. English is the only locale. The steps to add `docs/sk/` are on this page.

### Is the coverage report part of the docs build?

No. CI uploads it and the docs workflow copies it to `/coverage/` at deploy time.

### How do I report a mistake in the docs?

Use the edit link at the bottom of any page, or open an issue. See [Contributing](/contributing).
