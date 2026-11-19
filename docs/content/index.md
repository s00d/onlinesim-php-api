---
title: onlinesim-php-api
description: PHP 8.1+ client for the OnlineSim API — SMS numbers, rent, account and free numbers. ext-curl, no runtime dependencies.
---

## Install

PHP 8.1 or newer with `ext-curl` and `ext-json`. No other runtime dependencies.

```bash [composer]
composer require s00d/onlinesim-api
```

## Quick start

Check the balance, order a number, wait for the SMS code.

```php
use s00d\OnlineSimApi\OnlineSim;

$client = new OnlineSim(apiKey: getenv('ONLINESIM_KEY') ?: null);

$balance = $client->user->balance();
echo 'available: ', $balance['balance'] - $balance['zbalance'], " USD\n";

$order = $client->numbers->getWithNumber('google', 44);
echo "enter {$order['number']} in the app\n";

// Polls every 5 s for up to 10 min, then closes the operation.
$code = $client->numbers->waitCode($order['tzid']);
echo "code: {$code}\n";
```

## Client options

All constructor parameters are optional; pass them by name.

```php
use s00d\OnlineSimApi\OnlineSim;

$client = new OnlineSim(
    apiKey: 'your-api-key',
    lang: 'en',
    timeout: 15.0,
);
```

| Option | Type | Default | Description |
|---|---|---|---|
| `apiKey` | `?string` | `null` | Sent as `Authorization: Bearer <apiKey>`, never in the URL. Not needed for tariffs and free numbers. |
| `baseUrl` | `string` | `https://onlinesim.host/api/` | API base; trailing slash optional. |
| `lang` | `string` | `en` | Language of server texts (`lang` query parameter). |
| `devId` | `string\|int\|null` | `null` | Partner developer id (`dev_id`), sent only when set. |
| `timeout` | `float` | `30.0` | Per-request timeout, seconds. |
| `transport` | `?Transport` | `CurlTransport` | Sends the HTTP request; a fake in tests. |

Sections: [`$client->numbers`](/numbers), [`$client->rent`](/rent), [`$client->user`](/user), [`$client->free`](/free). Webhook bodies are parsed by the standalone function [`parseWebhook`](/webhooks).

Results are arrays with the server's snake_case keys; their shapes are declared as PHPStan types on each section class (`Numbers`, `Rent`, `User`, `Free`), so PHPStan and IDEs know every key. Two things are normalised: money that the server sends as a decimal string (`balance`, rent `sum`) becomes a `float`, and a missing map becomes `[]`.

### Transport

`Transport` is one method: `send(string $method, string $url, array $headers, ?string $body, float $timeout): Response` (`Response` has `status` and `body`). It throws `TransportError` only when no response arrived. The default `CurlTransport` takes extra `CURLOPT_*` options, e.g. a proxy:

```php
use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\Transport\CurlTransport;

$client = new OnlineSim(
    apiKey: 'your-api-key',
    transport: new CurlTransport([CURLOPT_PROXY => 'http://127.0.0.1:3128']),
);
```

## Error handling

Every error extends `s00d\OnlineSimApi\Exception\OnlineSimError` (a `RuntimeException`).

| Class | When | Fields |
|---|---|---|
| `ApiError` | The API answered with an error code (also a wrong key, HTTP 456). | `errorCode`, `getMessage()` |
| `NoNumberError` | `NO_NUMBER` / `NO_NUMBER_FOR_FORWARD`: no number right now. Subclass of `ApiError`. | `errorCode`, `getMessage()` |
| `TransportError` | Network error, request timeout, or a body that is not JSON. | `status` (`null` without a response), `body` (first 500 chars) |
| `TimeoutError` | `waitCode` got no code within its `timeout`. | — |

```php
use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;

try {
    $client->numbers->get('google', 44);
} catch (NoNumberError) {
    // try another country or later
} catch (ApiError $e) {
    echo $e->errorCode, ': ', $e->getMessage(), "\n"; // e.g. WARNING_LOW_BALANCE
} catch (TransportError $e) {
    echo 'network: ', $e->status, ' ', $e->body, "\n";
}
```

The raw code is `errorCode` (PHP's own `getCode()` is an integer and stays `0`). The message is the server's text when it sends one, otherwise a short English description of the code (`ApiError::MESSAGES`), otherwise the code itself. Profile validation failures become `ApiError` with code `VALIDATION_ERROR`.
