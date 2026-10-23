---
title: "Contributing"
description: "How to contribute to Hermes: coding style, tests, checks, and how to change the documentation diagrams."
---

# Contributing

Pull requests are welcome on [tomaj/hermes](https://github.com/tomaj/hermes). [CONTRIBUTING.md](https://github.com/tomaj/hermes/blob/master/CONTRIBUTING.md) is the project policy. This page matches that file. Participation follows the [code of conduct](https://github.com/tomaj/hermes/blob/master/CODE_OF_CONDUCT.md).

## Changes we can merge

- Follow [PER Coding Style](https://www.php-fig.org/per/coding-style/). `composer phpcs` checks `src`, `tests`, and `examples`.
- Add tests for every behaviour change.
- Document behaviour changes in `README.md`, `CHANGELOG.md`, and this guide (in English and in Slovak under `docs/sk/`). Keep examples on the signatures in `src/`, and describe what that code does, not a planned later version. [Versioning docs](/guide/versioning) says where a later version goes.
- [Semantic Versioning](https://semver.org/). The current stable line is 6.x. Breaking changes wait for the next major version.
- One pull request per change, from a feature branch. Do not open it from `master`.

## Checks

```bash
composer install
composer phpunit
composer phpcs
composer phpstan
composer rector
```

Integration tests talk to real brokers and are skipped unless their variable is set: `HERMES_TEST_REDIS` (`tcp://127.0.0.1:6379`), `HERMES_TEST_RABBITMQ` (host, port 5672, user `guest`), `HERMES_TEST_SQS` (an SQS endpoint such as moto on `http://127.0.0.1:4566`) and `HERMES_TEST_KAFKA` (bootstrap servers, `127.0.0.1:9092`; the Kafka tests also need `ext-rdkafka`). With `HERMES_REQUIRE_SERVICES=1` a missing or unreachable broker fails the test instead of skipping it. `compose.yaml` starts the same broker versions as CI, and `composer test:integration` sets all five variables:

```bash
docker compose up -d --wait
composer test:integration
```

Coverage:

```bash
composer coverage
```

GitHub Actions runs PHPUnit with Redis, RabbitMQ, moto (SQS) and Kafka on PHP 8.4, 8.4 with the lowest dependencies, and 8.5, so no integration test is skipped, plus code style, Rector, PHPStan level max, `composer audit`, Infection, CodeQL, OpenSSF Scorecard, and the documentation diagram and link checks.

## Diagrams

The hand-drawn diagrams are generated. Do not edit the SVG files by hand.

- `docs/diagrams/<name>.json` is the source: a hand-placed list of shapes, text, arrows, and small icons with exact coordinates.
- `npm run diagrams` in `docs/` turns each source into an [Excalidraw](https://excalidraw.com/) scene (`docs/diagrams/<name>.excalidraw`) and into `docs/public/diagrams/<name>-light.svg` and `<name>-dark.svg`. The Excalifont handwriting font is embedded in each SVG.
- A page shows a diagram with `<Diagram name="<name>" alt="What the diagram shows" />`. The light or dark file follows the site theme. The page shows it at most 420 pixels high, and a click opens it full screen.
- The script needs Chrome or Chromium. Set `CHROME_PATH` when it is installed somewhere unusual.
- Commit the source and all three generated files. `npm run docs:links` (the CI docs check) runs `npm run diagrams:check` first and fails when they are out of date.

```bash
cd docs
npm ci
npm run diagrams
```

The `.excalidraw` file can be opened on excalidraw.com to look at a diagram, but changes made there are overwritten on the next run. Change the `.json` source instead.

## Releases

A version tag (`6.0.0`, no `v` prefix) starts the release workflow: it takes the release notes from the `CHANGELOG.md` section of that version, builds the source archives, attests their build provenance and publishes the GitHub release. The steps are in [RELEASING.md](https://github.com/tomaj/hermes/blob/master/RELEASING.md).

## Security

Do not open a public issue for a vulnerability. Use [private vulnerability reporting](https://github.com/tomaj/hermes/security/advisories/new), or email [tomasmajer@gmail.com](mailto:tomasmajer@gmail.com) when that form is not available. Supported versions are listed in [SECURITY.md](https://github.com/tomaj/hermes/blob/master/SECURITY.md). 6.x is the supported line; 5.x gets security fixes only, until 2027-04-19.

## License

Contributions are under the [MIT license](https://github.com/tomaj/hermes/blob/master/LICENSE.md).

## FAQ

### Which checks must pass?

PHPUnit (with every integration test running), PHP-CS-Fixer, PHPStan level max, Rector, `composer audit`, Infection, and the docs diagram and link checks.

### How do I change a diagram?

Edit `docs/diagrams/<name>.json`, run `npm run diagrams` in `docs/`, and commit the source and the three generated files.

### Can I document a planned feature of a later major version?

Not on these pages: they describe the code in `src/`. See [Versioning docs](/guide/versioning).

### Which branch should a pull request come from?

A feature branch, one change per pull request.

### How is a release published?

By pushing a version tag. The release workflow publishes the GitHub release with attested archives; see [RELEASING.md](https://github.com/tomaj/hermes/blob/master/RELEASING.md).

### Where do I preview the docs?

Run `npm run docs:dev` in `docs/`.
