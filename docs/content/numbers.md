---
title: Numbers
description: Order a number, read its SMS, close it — $client->numbers.
navigation:
  order: 1
---

An operation (`tzid`) is one ordered number. Order it with `get`/`getWithNumber`, read the SMS with `stateOne` or `waitCode`, finish it with `close` (or `ban`). Countries are phone codes (`7`, `44`, …), services are slugs (`google`, `vkcom`, …) as returned by `tariffs`.

## price

Price of one number, USD; `null` when the country has no price for the service.

```php
public function price(string $service, int $country = 7): ?float
```

| Param | Type | Default | Description |
|---|---|---|---|
| `service` | `string` | — | Service slug. |
| `country` | `int` | `7` | Country code. |

```php
$usd = $client->numbers->price('google', 44);
```

Errors: `ApiError` `ERROR_NO_SERVICE`, `UNDEFINED_COUNTRY`.

## get

Order a number; returns the operation id. Freezes the price on the balance.

```php
public function get(string $service, int $country = 7, array $reject = []): int
```

| Param | Type | Default | Description |
|---|---|---|---|
| `service` | `string` | — | Service slug. |
| `country` | `int` | `7` | Country code. |
| `reject` | `list<string\|int>` | profile list | Number prefixes to skip, e.g. `['4470']`. |

```php
$tzid = $client->numbers->get('google', 44, reject: ['4470']);
```

Errors: `NoNumberError`; `ApiError` `WARNING_LOW_BALANCE`, `ERROR_NO_SERVICE`, `UNDEFINED_COUNTRY`, `EXCEEDED_CONCURRENT_OPERATIONS`, `INTERVAL_CONCURRENT_REQUESTS_ERROR`, `TRY_AGAIN_LATER`.

## getWithNumber

Same as `get`, but returns the number right away.

```php
public function getWithNumber(string $service, int $country = 7, array $reject = []): array
```

Returns `NumberOrder`: `['tzid', 'number', 'country', 'time', 'service', 'title'?, 'guard_interval_remaining_seconds'?]` — `number` with `+`, `time` in seconds left.

```php
$order = $client->numbers->getWithNumber('google', 44);
echo $order['tzid'], ' ', $order['number'];
```

Errors: as `get`.

## state

All operations of the account; `[]` when there are none.

```php
public function state(bool $messageToCode = true, OrderBy $orderBy = OrderBy::Asc, bool $msgList = false): array
```

| Param | Type | Default | Description |
|---|---|---|---|
| `messageToCode` | `bool` | `true` | `msg` is the code only; `false` — the full SMS text. |
| `orderBy` | `OrderBy` | `OrderBy::Asc` | Order of operations (`OrderBy::Asc`, `OrderBy::Desc`). |
| `msgList` | `bool` | `false` | `msg` is a list of all SMS (`OperationSms[]`). |

Returns `list<Operation>`; `Operation`: `['tzid', 'response', 'number'?, 'service'?, 'country'?, 'sum'?, 'time'?, 'msg'?, 'guard_interval_remaining_seconds'?, …]`.
`response` is the state: `TZ_INPOOL` (number being assigned), `TZ_NUM_WAIT` (waiting for SMS), `TZ_NUM_ANSWER` (SMS arrived), `TZ_OVER_OK` (finished), `ERROR_NO_ITEMS` (failed). `msg` is absent until the first SMS. The server caches answers for a few seconds.

```php
use s00d\OnlineSimApi\OrderBy;

foreach ($client->numbers->state(orderBy: OrderBy::Desc) as $op) {
    echo $op['tzid'], ' ', $op['response'], ' ', $op['msg'] ?? '-', "\n";
}
```

## stateOne

One operation.

```php
public function stateOne(int $tzid, bool $messageToCode = true, bool $msgList = false): array
```

```php
$op = $client->numbers->stateOne($tzid);
if ($op['response'] === 'TZ_NUM_ANSWER') {
    echo $op['msg'];
}
```

Errors: `ApiError` `ERROR_NO_OPERATIONS` when the operation does not exist (any more).

## waitCode

Poll the operation until a code arrives, then close it and return the code.

```php
public function waitCode(int $tzid, float $interval = 5.0, float $timeout = 600.0, bool $keepOpen = false, bool $fullMessage = false, ?string $since = null, ?callable $sleep = null, ?callable $now = null): string
```

| Param | Type | Default | Description |
|---|---|---|---|
| `interval` | `float` | `5.0` | Poll interval, seconds. |
| `timeout` | `float` | `600.0` | Give up after, seconds. |
| `keepOpen` | `bool` | `false` | Call `next($tzid)` instead of `close($tzid)`, to wait for another SMS. |
| `fullMessage` | `bool` | `false` | Return the full SMS text instead of the code. |
| `since` | `?string` | `null` | Ignore this value (the previous code). |
| `sleep`, `now` | callables | `usleep`, `hrtime` | `sleep(float $seconds)` and a monotonic `now(): float` in seconds; injectable for tests. |

A transient `TransportError` is retried until the timeout; any `ApiError` is thrown at once.

```php
$first = $client->numbers->waitCode($tzid, keepOpen: true);
$second = $client->numbers->waitCode($tzid, since: $first);
```

Errors: `TimeoutError`; `ApiError` with code `TZ_OVER_OK` or `ERROR_NO_ITEMS` when the operation ended without a new code; any other `ApiError` from `stateOne`/`close`/`next`.

## next

Switch the operation to its next SMS and keep it open (for services that send several SMS). Returns `true`.

```php
public function next(int $tzid): bool
```

```php
$client->numbers->next($tzid);
```

## close

Finish the operation. An operation without SMS can be closed only after its guard interval (`guard_interval_remaining_seconds`); every attempt locks the tzid for 5 s. Returns `true`.

```php
public function close(int $tzid): bool
```

```php
$client->numbers->close($tzid);
```

Errors: `ApiError` `NO_COMPLETE_TZID`, `INTERVAL_CONCURRENT_REQUESTS_ERROR`, `ERROR_WRONG_TZID`.

## ban

Finish the operation and report the number as bad. Only before any SMS arrived. Returns `true`.

```php
public function ban(int $tzid): bool
```

```php
$client->numbers->ban($tzid);
```

Errors: as `close`.

## tariffs

Services, prices and stock of every country, keyed by country code. No API key needed. The answer is large (a few MB); prefer `tariffsOne`.

```php
public function tariffs(): array
```

Returns `array<int, CountryStat>`; `CountryStat`: `['name', 'locale_name'?, 'position', 'code', 'other'?, 'new', 'enabled', 'services']`; `services` maps `service_<slug>` to `['count', 'popular', 'code', 'price', 'id', 'service', 'slug']`.

```php
$all = $client->numbers->tariffs();
echo count($all), " countries\n";
```

## tariffsOne

Services, prices and stock of one country.

```php
public function tariffsOne(int $country = 7): array
```

```php
$uk = $client->numbers->tariffsOne(44);
foreach ($uk['services'] as $s) {
    echo $s['slug'], ' ', $s['price'], ' ', $s['count'], "\n";
}
```

Errors: `ApiError` `NO_COUNTRY`.
