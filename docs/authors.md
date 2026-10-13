---
title: "Authors and contributors"
description: "Who created and maintains Hermes, the people who contributed to it, and the release history since 2015."
---

# Authors and contributors

<AuthorsPage />

The facts above come from the public GitHub profile, and the list from the GitHub contributors API for `tomaj/hermes`. `node scripts/update-authors.mjs` in `docs/` refreshes them.

## Project history

The first commit is from 12 October 2015. Dates are the release tags.

| Version | Date | What changed |
| --- | --- | --- |
| 0.1.0 | 2015-10-28 | First tagged release |
| 1.0.0 | 2016-09-02 | First stable version. Acknowledgements in the RabbitMQ driver |
| 2.0.0 | 2018-10-17 | Scheduled messages (`executeAt`), type hints, `Emitter` replaces `Dispatcher::emit()` |
| 3.0.0 | 2020-10-13 | Graceful shutdown through Redis. The ZeroMQ driver moved to [its own package](https://github.com/tomaj/hermes-zmq-driver) |
| 4.0.0 | 2021-02-02 | `Restart` renamed to `Shutdown`, `PredisSetDriver` split from `RedisSetDriver`, `LazyRabbitMqDriver` only |
| 4.2.0 | 2024-04-29 | `unregisterHandler()` and `unregisterAllHandlers()` |
| 5.0.0 | 2025-11-19 | Current major version, documented on this site |

The full list is in the [Changelog](/changelog) and on [GitHub releases](https://github.com/tomaj/hermes/releases).

## Become a contributor

Bug reports, documentation fixes, and pull requests are welcome. The [Contributing](/contributing) page lists the checks a pull request must pass.

## FAQ

### Who maintains Hermes?

Tomas Majer ([@tomaj](https://github.com/tomaj)) created the project and maintains it.

### How do I become a contributor?

Open a pull request from a feature branch. The [Contributing](/contributing) page lists the checks it must pass.

### Where does the contributor list come from?

From the GitHub contributors API for `tomaj/hermes`, saved when the docs were last updated.

### When did the project start?

The first commit is from 2015. The release history is on this page and in the [Changelog](/changelog).

### Where do I report a bug?

In [GitHub issues](https://github.com/tomaj/hermes/issues).
