# Security Policy

## Supported Versions

Security fixes are provided for the lines below.

| Version | Supported |
| ------- | --------- |
| 6.x     | Yes: bug fixes and security fixes |
| 5.x     | Security fixes only, until 2027-04-19 |
| < 5.0   | No |

6.x is the current stable line. 5.x gets fixes for security vulnerabilities only, for six months after the 6.0.0 release (until 2027-04-19); there is no 5.x maintenance branch, so a fix is released from one created from the `5.0.0` tag. Upgrade to 6.x with [UPGRADE-6.0.md](UPGRADE-6.0.md). Versions older than 5.0 do not receive security updates.

## Reporting a Vulnerability

Do not open a public GitHub issue for a security vulnerability.

The preferred way to report a vulnerability is [GitHub private vulnerability reporting](https://github.com/tomaj/hermes/security/advisories/new).

If you cannot use private vulnerability reporting, email the details to **tomasmajer@gmail.com**.

Please include:

- A description of the vulnerability
- Steps to reproduce, or a proof of concept
- The Hermes version and the queue driver involved
- The potential impact
- A suggested fix, if you have one

### Response

You will receive an acknowledgement within 14 days.

### Coordinated disclosure

We follow coordinated disclosure. Please give us time to prepare a fix before discussing the issue in public.

When a fix is ready, we publish a [GitHub Security Advisory](https://github.com/tomaj/hermes/security/advisories) and record the security fix in the [CHANGELOG](CHANGELOG.md).

## Security considerations

Hermes moves job payloads through Redis, RabbitMQ, Amazon SQS, or a custom driver. Treat the queue as a trusted channel:

- Keep credentials for Redis, RabbitMQ, and AWS in the environment or a secret store. Do not commit them.
- Payloads can contain personal or business data. Limit who can publish and consume messages, and avoid logging full payloads.
- Run workers with the least privilege they need, and keep Hermes and its driver clients up to date.
