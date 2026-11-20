# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1]

Aligned with the regenerated backend API spec.

### Fixed

- `numbers->waitCode()` read the getState `response` key unguarded: an operation without it (no number issued yet) raised an "Undefined array key" warning (an `ErrorException` where warnings are converted, e.g. Laravel) and stopped the wait; it now keeps polling.
- PHPStan types: `Profile` `number` is `int|null` and `country` `string|null` (they were swapped); `Operation` `response` is optional; `RentSms` `code` is `string|int` (the integer `0` for a `notify` SMS, kept as sent); `FreeCountry` `country_text`/`country_original`, `FreeNumber` `country_original`, `PaymentOrder` `currency`/`form_text` accept `null`; `NumberOrder` gains `response_text`. `paymentHistory()` keeps a `paylist` that arrives as a JSON array (ids 0, 1, 2…) as that list.
- `numbers->waitCode()` retries `ApiError` `TRY_AGAIN_LATER` (HTTP 429 rate limit), `ERROR_UNDEFINED` and `INTERVAL_CONCURRENT_REQUESTS_ERROR` until its timeout instead of throwing at once.
- Error messages: `"0"` (ban not applied, the operation already has an SMS), `ACCOUNT_CLEANED`, `USER_NOT_ACTIVATED`, `ERROR_UNDEFINED`, `FORWARD_NOT_SUPPORTED` added; `ERROR_NO_KEY`, `NO_NUMBER_FOR_FORWARD`, `ACCOUNT_IDENTIFICATION_REQUIRED`, `API_DISABLED` (no longer sent) removed; `ERROR_WRONG_KEY` reads "Wrong or missing API key". `NoNumberError` is raised only for `NO_NUMBER`.
- `MockOnlineSim` follows the backend: `setOperationRevise` answers `{"tzid":"N","response":"1"}` and `ERROR_WRONG_TZID` / `ERROR_NO_TZID`; `setOperationOk` tzid `0` → `ERROR_NO_TZID`; a missing key is HTTP 200 `ERROR_WRONG_KEY` (456 only for a wrong key, also on public endpoints); `getNum` with `number` adds `title`, `response_text`, `guard_interval_remaining_seconds`. Rent: `getRentNum` reserves the price (`status` 0) and the first non-`notify` SMS confirms and charges it, unknown country → `UNDEFINED_COUNTRY`, `sum` a number in getRentNum/extendRentState and a string in getRentState, `guard_interval_remaining_seconds` with `number`; `extendRentState` refusals are all `TRY_AGAIN_LATER`; `closeRentNum` unknown tzid → `ERROR_NO_OPERATIONS`, releases the reservation of an unconfirmed rent. Webhook-log params: `code` `""` without a code, rent `number` in full digits, rent `time_left` in minutes, `time_start` per type. `profile.php` accepts any `scheme://…` URL. `getPaymentHistory` adds `forms`/`buttons`; `getFreeList` lists 44 first, clamps `count` to 10..500, sends ISO-8601 `created_at` and `ignore`/`content`.
- Docs: rent reservation and `sum`, available = balance − max(zbalance, 0), `ban` answer `"0"`, webhook `number`/`time_left`/`time_start`, the `waitCode` retry policy, the mock's behaviour.

## [2.1.0]

### Added

- `MockOnlineSim` (`s00d\OnlineSimApi\Mock\MockOnlineSim`): a built-in, in-process fake of the OnlineSim API for testing integrations offline — no network, key, money or clock. `$mock->client()` returns a normal `OnlineSim` wired to it; the mock is itself a `Transport` (`$mock->transport`). It answers every endpoint the SDK calls like the real backend (HTTP 456 for a wrong key, string/int `response`, decimal-string money, `[]` for empty maps) and keeps balance, operations and rents. Test helpers: `addSms`, `addSmsAfter` (lets `waitCode` run with `interval: 0`), `failNext`, `failNextTransport`, `setBalance`, `balance`, `operations`, `rents`, `requests`, `reset`. Part of the package (autoloaded only when used). See the [testing guide](https://s00d.github.io/onlinesim-php-api/testing).
- Packaging checks in CI: `composer validate --strict`, `composer normalize --dry-run` (ergebnis/composer-normalize), and a smoke test that installs the `git archive` of the tree into an empty Composer project and runs the README "Testing" example on PHP 8.1 and 8.4.
- Release workflow: a version tag runs the CI and creates the GitHub release from this changelog (Packagist updates by webhook).

### Changed

- `composer.json` normalized (key order only).

## [2.0.0]

A rewrite. The public API changed; see the [docs](https://s00d.github.io/onlinesim-php-api/).

### Removed

- The proxy API (`$api->proxy()`, deprecated by the backend).
- `numbers()->repeat` (getNumRepeat), `numbers()->service` (getService), `numbers()->serviceNumber` (getServiceNumber): no backend route.
- `rent()->portReload`: deprecated no-op on the backend.
- `free()->countries`, `free()->numbers`, `free()->messages`: the backend returns empty data for them; use `$client->free->list()`.
- `init()`, `user()->me()` and `Request::setDomain()` (it rebuilt the base URL and forced `https://`); pass `baseUrl` instead.
- The `Responses\*` classes, `NoAuthException`, the localized `RequestException` messages.
- `Diagrams/`, `.env.example`, the committed `composer.lock`, the duplicate `classmap` and the `Tests\` namespace in the production autoload.

### Changed

- One client: `new OnlineSim(apiKey:, baseUrl:, lang:, devId:, timeout:, transport:)`; the sections are readonly properties (`$client->numbers`, `->rent`, `->user`, `->free`) instead of factory methods, and no section extends another.
- Requires PHP >= 8.1 with ext-curl and ext-json; HTTP goes through a small `Transport` interface (default `CurlTransport`) instead of `file_get_contents`.
- The API key is sent as `Authorization: Bearer <key>`, never in the query string.
- Errors: `OnlineSimError` → `ApiError` (`errorCode`, message), `NoNumberError` (subclass of `ApiError`), `TransportError` (`status`, `body`), `TimeoutError`. They replace `RequestException` / `NoNumberException` and are no longer caught and re-wrapped inside the SDK.
- Results are arrays with PHPStan shapes; money parsed to floats (`user->balance()['balance']`, rent `sum`).
- `numbers->tariffs($country)` split into `tariffs()` and `tariffsOne($country)`; `rent->tariffs($country)` likewise.
- `numbers->get($service, $country, reject: [...])`; the meaningless `extension` argument is gone. `getWithNumber` returns `tzid, number, country, time, service`.
- `numbers->state(messageToCode:, orderBy: OrderBy::Asc|Desc, msgList:)` / `stateOne($tzid, …)` take booleans and an enum.
- `rent->get($country, $days, $extension)`: `extension` defaults to `true` (the server default; `false` makes the rent non-extendable for good).
- License metadata in composer.json matches the LICENSE file (Apache-2.0).

### Fixed

- `lang` was never sent; it is now sent with every request (default `en`).
- `numbers()->state()` returned `true` instead of the list (`$result || []`).
- The base URL could not be overridden; `baseUrl` is a constructor option.
- POST requests were sent without the `.php` suffix and as form data; `profile.php` now gets a JSON body.
- `reject` is sent as a comma list; booleans as `1`/`0`; unset parameters are omitted; `orderby` is lowercase.
- An HTTP 456 wrong-key answer is an `ApiError`; non-JSON answers and network errors raise `TransportError` instead of PHP warnings.
- `state()` returns `[]` when there are no operations; `stateOne()` / `rent->stateOne()` raise `ERROR_NO_OPERATIONS` instead of an undefined-offset warning.
- Rent requests always send `pagination=0` (flat `messages`) and `number=1` (`time_seconds`).
- The tests no longer hit the live API.

### Added

- `numbers->waitCode($tzid, interval:, timeout:, keepOpen:, fullMessage:, since:)` with injectable `sleep` / `now`.
- `user->paymentHistory($page)`, `user->setWebhookUrl($url)`, `user->clearWebhookUrl()`, `user->webhookLogs($page)`, `free->list(...)`.
- `parseWebhook($payload)` for inbound `receiving_sms` / `rent_sms` webhooks.
- Offline test suite (PHPUnit, fake transport), opt-in integration test, PHPStan level max, PHP-CS-Fixer (PSR-12), CI on PHP 8.1–8.4.
- Documentation site (Nuxt Content): https://s00d.github.io/onlinesim-php-api/

## [1.3.4] - 2024-11-29

Released without a changelog entry: dependency update.

## [1.3.3] - 2023-02-22

Released without a changelog entry: default API host changed from `onlinesim.ru` to `onlinesim.host`.

## [1.3.2] - 2022-08-04
- add init
- remove io

## [1.3.1] - 2022-08-04
- add io
- fix tests

## [1.1.10] - 2022-05-06
- add default locale

## [1.1.9] - 2022-05-06
### Changed
- fix locale
- update deps

## [1.1.7] - 2020-08-11
### Changed
- update docs

## [1.1.6] - 2020-05-02
### Changed
- update docs

## [1.1.5] - 2020-05-02
### Changed
- add Diagrams
- update free api
- update wiki
- rename params

## [1.1.3] - 2020-05-02
### Changed
- fix getNumber close
- added getNumber next

## [1.1.0] - 2020-05-01
### Changed
- Refactoring
- Tests - 70%
- Responses
- Docs

## [1.0.2] - 2020-04-31
### Added
- function to scripts
### Changed
- refactoring

## [1.0.0] - 2020-04-31
### Changed
- init
