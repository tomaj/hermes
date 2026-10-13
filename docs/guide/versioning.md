# Versioning docs

These pages describe Hermes 5.x, the current code in `src/`. They are the site root (`/hermes/` on GitHub Pages).

<Diagram name="versioning-1" alt="Versioning docs diagram" />

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
