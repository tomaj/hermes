---
title: "Contributing"
description: "How to contribute to Hermes: coding style, tests, checks, and how to change the documentation diagrams."
---

# Contributing

Pull requests are welcome on [tomaj/hermes](https://github.com/tomaj/hermes). [CONTRIBUTING.md](https://github.com/tomaj/hermes/blob/master/CONTRIBUTING.md) is the project policy. This page matches that file. Participation follows the [code of conduct](https://github.com/tomaj/hermes/blob/master/CODE_OF_CONDUCT.md).

## Changes we can merge

- Follow [PER Coding Style](https://www.php-fig.org/per/coding-style/). `composer phpcs` checks `src`, `tests`, and `examples`.
- Add tests for every behaviour change.
- Document behaviour changes in `README.md`, `CHANGELOG.md`, and this guide. Keep examples on the signatures in `src/`. A 6.x behavior should not be written into the 5.x pages. [Versioning docs](/guide/versioning) says where a later version goes.
- [Semantic Versioning](https://semver.org/). The current stable line is 5.x. Breaking changes belong in the planned 6.0 release.
- One pull request per change, from a feature branch. Do not open it from `master`.

## Checks

```bash
composer install
composer phpunit
composer phpcs
composer phpstan
```

`make test` is PHPUnit as well. Coverage:

```bash
composer coverage
```

GitHub Actions runs PHPUnit on PHP 8.4, 8.4 with the lowest dependencies, and 8.5, plus code style, PHPStan level max, `composer audit`, Infection, CodeQL, OpenSSF Scorecard, and the documentation diagram and link checks.

## Diagrams

The hand-drawn diagrams are generated. Do not edit the SVG files by hand.

- `docs/diagrams/<name>.json` is the source: a hand-placed list of shapes, text, arrows, and small icons with exact coordinates. A `docs/diagrams/<name>.mmd` source in [Mermaid](https://mermaid.js.org/) flowchart or sequence syntax also works and is laid out automatically.
- `npm run diagrams` in `docs/` turns each source into an [Excalidraw](https://excalidraw.com/) scene (`docs/diagrams/<name>.excalidraw`) and into `docs/public/diagrams/<name>-light.svg` and `<name>-dark.svg`. The Excalifont handwriting font is embedded in each SVG.
- A page shows a diagram with `<Diagram name="<name>" alt="What the diagram shows" />`. The light or dark file follows the site theme. The page shows it at most 420 pixels high, and a click opens it full screen.
- The script needs Chrome or Chromium. Set `CHROME_PATH` when it is installed somewhere unusual.
- Commit the source and all three generated files. `npm run docs:links` (the CI docs check) runs `npm run diagrams:check` first and fails when they are out of date.

```bash
cd docs
npm ci
npm run diagrams
```

The `.excalidraw` file can be opened on excalidraw.com to look at a diagram, but changes made there are overwritten on the next run. Change the `.json` or `.mmd` source instead.

## Security

Do not open a public issue for a vulnerability. Use [private vulnerability reporting](https://github.com/tomaj/hermes/security/advisories/new), or email [tomasmajer@gmail.com](mailto:tomasmajer@gmail.com) when that form is not available. Supported versions are listed in [SECURITY.md](https://github.com/tomaj/hermes/blob/master/SECURITY.md). 5.x is the supported line until 6.0 is released.

## License

Contributions are under the [MIT license](https://github.com/tomaj/hermes/blob/master/LICENSE.md).

## FAQ

### Which checks must pass?

PHPUnit, PHP-CS-Fixer, PHPStan level max, `composer audit`, Infection, and the docs diagram and link checks.

### How do I change a diagram?

Edit `docs/diagrams/<name>.json` or `.mmd`, run `npm run diagrams` in `docs/`, and commit the source and the three generated files.

### Can I document a planned 6.x feature?

Not on the 5.x pages. See [Versioning docs](/guide/versioning).

### Which branch should a pull request come from?

A feature branch, one change per pull request.

### Where do I preview the docs?

Run `npm run docs:dev` in `docs/`.
