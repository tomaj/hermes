# Contributing

Contributions are welcome and will be credited.

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

Security vulnerabilities are reported in private. See [SECURITY.md](SECURITY.md). Do not open a public issue for a security report.

## Pull requests

- Follow [PER Coding Style](https://www.php-fig.org/per/coding-style/). `composer phpcs` checks `src`, `tests`, and `examples`.
- Add tests for every behaviour change. A patch without tests will not be accepted.
- Document behaviour changes in `README.md` and `CHANGELOG.md`.
- Follow [Semantic Versioning](https://semver.org/). The current stable line is 5.x. Breaking changes belong in the planned 6.0 release.
- Open one pull request per change, from a feature branch. Do not ask us to pull from your `master` branch.
- Keep the history coherent. Squash work-in-progress commits before submitting.

## Development

Hermes supports PHP 8.4 and 8.5. Continuous integration runs on PHP 8.4, PHP 8.4 with the lowest dependencies, and PHP 8.5.

```bash
git clone https://github.com/tomaj/hermes.git
cd hermes
composer install
composer phpunit
composer phpstan
composer phpcs
composer rector
```

PHPUnit fails on any notice, deprecation or warning, including PHPUnit's own (for example a mock without expectations: use `createStub()`). Deprecations that one dependency raises inside another are ignored, so the lowest supported dependencies still pass.

Integration tests talk to real brokers and are skipped unless their variable is set: `HERMES_TEST_REDIS` (`tcp://127.0.0.1:6379`), `HERMES_TEST_RABBITMQ` (host, port 5672, user `guest`) and `HERMES_TEST_SQS` (an SQS endpoint such as moto on `http://127.0.0.1:4566`). With `HERMES_REQUIRE_SERVICES=1` a missing or unreachable broker fails the test instead of skipping it. `compose.yaml` starts the same broker versions as CI, and `composer test:integration` sets all four variables:

```bash
docker compose up -d --wait
composer test:integration
```

To generate an HTML and Clover coverage report (requires Xdebug or PCOV):

```bash
composer coverage
```

By contributing, you agree that your contributions are licensed under the MIT License.
