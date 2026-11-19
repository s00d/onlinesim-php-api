---
title: Testing
description: Test your integration offline with MockOnlineSim — no network, key or money.
navigation:
  order: 6
---

`MockOnlineSim` is an in-process fake of the OnlineSim API that ships with the package. It is a `Transport` that answers like the real backend — same shapes, same error codes, HTTP 456 for a wrong key — and keeps a balance, operations and rents. Your code talks to a normal `OnlineSim` client, so nothing in it changes for tests. It is deterministic: no randomness, no clock, numbers and ids come from a counter.

## Setup

```php
use s00d\OnlineSimApi\Mock\MockOnlineSim;

$mock = new MockOnlineSim(balance: 10);
$client = $mock->client(); // an OnlineSim wired to the mock
```

```php
public function __construct(string $apiKey = 'mock-key', float $balance = 100, ?array $prices = null, ?array $rentTariffs = null, int $guardInterval = 0)
```

| Option | Default | Description |
|---|---|---|
| `apiKey` | `'mock-key'` | Key the mock accepts. `$mock->client()` uses it; `$mock->client(apiKey: 'x')` tests a wrong key, `apiKey: ''` a missing one. |
| `balance` | `100` | Starting balance, USD. |
| `prices` | `[7 => ['vkcom' => 0.5, 'telegram' => 1.2, 'google' => 0.3], 44 => ['google' => 0.2, 'vkcom' => 0.36]]` | Number prices; replaces the defaults. Also feeds `numbers->tariffs()`. |
| `rentTariffs` | `[7 => ['days' => [1 => 1.0, 3 => 2.5, 7 => 5.0], 'extend' => 1]]` | Rent prices; `'extend' => 0` makes rents of that country non-extendable. |
| `guardInterval` | `0` | `> 0`: an operation without SMS cannot be closed (`NO_COMPLETE_TZID`). |

`$mock->client()` takes the usual [client options](/#client-options) except `transport`: `apiKey`, `baseUrl`, `lang`, `devId`, `timeout`. To build the client yourself, pass `$mock->transport` (the mock itself) as `transport`; any `baseUrl` works:

```php
use s00d\OnlineSimApi\Mock\MockOnlineSim;
use s00d\OnlineSimApi\OnlineSim;

$mock = new MockOnlineSim();
$client = new OnlineSim(apiKey: 'mock-key', transport: $mock->transport);
```

## A full example

Order a number, let an SMS arrive while `waitCode` polls, check the code and the money. `addSmsAfter` delivers the SMS after a number of polls instead of after some time, so `waitCode` runs with `interval: 0` and the test takes milliseconds.

```php
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\Mock\MockOnlineSim;

final class ReceiveCodeTest extends TestCase
{
    public function testReceivesTheCode(): void
    {
        $mock = new MockOnlineSim(balance: 10);
        $client = $mock->client();

        $order = $client->numbers->getWithNumber('google', 44);
        self::assertSame('+44900000001', $order['number']);

        $mock->addSmsAfter($order['tzid'], 'Your Google code: 123-456', 2); // the third poll sees it
        $code = $client->numbers->waitCode($order['tzid'], interval: 0);

        self::assertSame('123456', $code);
        self::assertSame('TZ_OVER_OK', $mock->operations()[0]['status']); // waitCode closed it
        self::assertSame(9.8, $mock->balance()); // the price is spent once an SMS arrived
    }
}
```

The `msg` of an operation is the code by default: the first run of 4–8 digits, with spaces and dashes between digits removed (`123-456` → `123456`); a text without such a run comes back whole. `messageToCode: false` (or `fullMessage: true` in `waitCode`) gives the full text.

## Errors

`failNext($code)` makes the next request answer `{"response": code}` — the client turns it into `ApiError` (or `NoNumberError`) exactly as for the real server. Pass `endpoint` to fail only that endpoint. `failNextTransport()` makes the next request fail with a non-JSON HTTP 502 (`TransportError`).

```php
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\Mock\MockOnlineSim;

final class ErrorsTest extends TestCase
{
    public function testHandlesAMissingNumberAndANetworkHiccup(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();

        $mock->failNext('NO_NUMBER', endpoint: 'getNum.php');
        try {
            $client->numbers->get('google');
            self::fail('expected NoNumberError');
        } catch (NoNumberError) {
        }

        $mock->failNextTransport(status: 503);
        $this->expectException(TransportError::class);
        $client->user->balance();
    }
}
```

The state itself produces the real error codes too: `WARNING_LOW_BALANCE` when the balance is short, `NO_NUMBER` when `reject` matches the next number, `UNDEFINED_COUNTRY` / `ERROR_NO_SERVICE` for unknown prices, `ERROR_WRONG_TZID` for an unknown or closed operation, `TRY_AGAIN_LATER` when extending a non-extendable rent, `ERROR_WRONG_KEY` (HTTP 456) for a wrong key.

## Inspecting requests

`$mock->requests()` lists every request received: `['method', 'path', 'query', 'body']`, `path` relative to `/api/`, `body` the decoded JSON of a POST (`null` for GET).

```php
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\Mock\MockOnlineSim;

final class RejectTest extends TestCase
{
    public function testSendsRejectAsACommaList(): void
    {
        $mock = new MockOnlineSim();
        $mock->client()->numbers->get('google', 44, reject: ['4470', '4471']);

        self::assertSame([
            ['method' => 'GET', 'path' => 'getNum.php', 'query' => ['service' => 'google', 'country' => '44', 'reject' => '4470,4471', 'lang' => 'en'], 'body' => null],
        ], $mock->requests());
    }
}
```

## Control API

| Member | Description |
|---|---|
| `client(...)` | A normal `OnlineSim` wired to the mock. |
| `transport` | The fake `Transport` (the mock itself). |
| `addSms($tzid, $text, service: null)` | An SMS arrives now at a number operation or a rent (detected by `tzid`). Throws `InvalidArgumentException` for an unknown or closed one. |
| `addSmsAfter($tzid, $text, $polls, service: null)` | The SMS arrives after `$polls` state requests for `tzid` (`numbers->state()`/`stateOne()`, `rent->state()`/`stateOne()`). |
| `failNext($code, endpoint: null)` | The next request (to `endpoint` only, e.g. `'getNum.php'`) answers `{"response": code}`. |
| `failNextTransport(status: 502, endpoint: null)` | The next request fails with a non-JSON body and HTTP `status`. |
| `setBalance($usd)` / `balance()` | Set / read the total balance, USD (it includes the part frozen by open operations, as `user->balance()` reports it). |
| `operations()` / `rents()` | Copies of the current number operations / open rents, for assertions. |
| `requests()` | Log of the requests received. |
| `reset()` | Back to the constructor's state. |

## What the mock does

- **Numbers**: `get` freezes the price; `close` after an SMS spends it, `close` without one refunds it; `ban` after an SMS answers `"0"`, like the server. Numbers are `+<country>9000000NN`, ids count from 1. `next` switches to the following SMS. The operation's `time` stays at 1200 s — there is no clock.
- **Rent**: `get` charges at once and keeps the `extension` flag; `extend` charges again; `close` removes the rent. SMS appear in `messages`, newest first.
- **User**: `balance` (frozen part included), a fixed profile, `setWebhookUrl` validates `http(s)` URLs (`VALIDATION_ERROR` otherwise), `webhookLogs` lists the deliveries the mock *recorded* when an SMS arrived while a webhook URL was set — it makes no HTTP call. `paymentHistory` is empty.
- **Free**: `free->list()` returns two fixed countries with one number and a few SMS each.
- Not imitated: the server's 5 s lock after `setOperationOk` (`INTERVAL_CONCURRENT_REQUESTS_ERROR`) and anything else that needs real time.
