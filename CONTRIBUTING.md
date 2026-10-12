# Contributing

Contributions are welcome and will be credited.

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

Security vulnerabilities are reported in private. See [SECURITY.md](SECURITY.md). Do not open a public issue for a security report.

## Pull requests

- Follow [PSR-2](https://www.php-fig.org/psr/psr-2/). `composer phpcs` checks `src`, `tests`, and `examples`.
- Add tests for every behaviour change. A patch without tests will not be accepted.
- Document behaviour changes in `README.md` and `CHANGELOG.md`.
- Follow [Semantic Versioning](https://semver.org/). The current stable line is 5.x. Breaking changes belong in the planned 6.0 release.
- Open one pull request per change, from a feature branch. Do not ask us to pull from your `master` branch.
- Keep the history coherent. Squash work-in-progress commits before submitting.

## Development

Hermes supports PHP 7.4 and 8.x. Continuous integration runs on PHP 7.4, 8.0, 8.1, 8.2, and 8.3.

```bash
git clone https://github.com/tomaj/hermes.git
cd hermes
composer install
composer phpunit
composer phpstan
composer phpcs
```

To generate an HTML and Clover coverage report (requires Xdebug or PCOV):

```bash
composer coverage
```

By contributing, you agree that your contributions are licensed under the MIT License.
