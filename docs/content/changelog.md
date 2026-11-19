---
title: Changelog
description: Release notes of onlinesim-php-api.
navigation:
  order: 6
---

The full history is in [CHANGELOG.md](https://github.com/s00d/onlinesim-php-api/blob/master/CHANGELOG.md).

## 2.0.0

A rewrite with a new API; 1.x code does not run unchanged.

- One client, `new OnlineSim(apiKey: …)`, with the sections as properties: `$client->numbers`, `$client->rent`, `$client->user`, `$client->free`.
- PHP 8.1+, ext-curl behind a small `Transport` interface, no runtime dependencies.
- The key is sent as a Bearer header, never in the URL; `lang` is sent; the base URL is configurable.
- Typed errors: `ApiError` (`errorCode`), `NoNumberError`, `TransportError`, `TimeoutError`.
- Results are arrays with PHPStan shapes instead of response classes; `tariffs($country)` is split into `tariffs()` and `tariffsOne($country)`.
- `rent->get` creates extendable rents by default (`extension: true`).
- New: `numbers->waitCode` (poll until the SMS code, then close), `user->setWebhookUrl` / `clearWebhookUrl` / `webhookLogs`, `parseWebhook`.
- Removed: the proxy API, `numbers->repeat`, `numbers->service`, `numbers->serviceNumber`, `rent->portReload`, `free->countries` / `numbers` / `messages` (use `free->list`), `init()` / `user->me()`, the response classes.
