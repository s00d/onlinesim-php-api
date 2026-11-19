---
title: User
description: Balance, profile, payments and the webhook URL — $client->user.
navigation:
  order: 3
---

## balance

Balance in USD.

```php
public function balance(): array
```

Returns `['balance' => float, 'zbalance' => float, 'income' => float]` (`income` only when the server sends it). `balance` includes the sum frozen by numbers in progress (`zbalance`): available = `balance − zbalance`. `income` is the partner income. The server caches the answer for 60 s.

```php
$b = $client->user->balance();
$available = $b['balance'] - $b['zbalance'];
```

Errors: `ApiError` `ERROR_WRONG_KEY`, `ACCOUNT_BLOCKED`, `API_ACCESS_DISABLED`, `API_ACCESS_IP`.

## profile

The account profile.

```php
public function profile(): array
```

`Profile`: `['id', 'name', 'username', 'email', 'locale', 'currency', 'number_country', 'number_reject', 'webhook_url', 'api_access', …]`.

```php
$profile = $client->user->profile();
echo $profile['email'] ?? '', ' ', $profile['webhook_url'] ?? 'no webhook';
```

## paymentHistory

Top-up orders (paginated, newest first) and the available payment methods.

```php
public function paymentHistory(int $page = 1): array
```

`PaymentHistory`: `['orders' => Paginator<PaymentOrder>, 'paylist', 'domains'?, 'forms'?, 'currency'?, 'buttons'?]`. `PaymentOrder['sum']` stays a decimal string (`"89.00"`).

```php
$orders = $client->user->paymentHistory()['orders'];
echo "{$orders['total']} orders, page {$orders['current_page']}/{$orders['last_page']}\n";
```

## setWebhookUrl

Set the URL that receives SMS webhooks (see [Webhooks](/webhooks)). Returns `true`.

```php
public function setWebhookUrl(string $url): bool
```

```php
$client->user->setWebhookUrl('https://example.com/onlinesim-hook');
```

Errors: `ApiError` `VALIDATION_ERROR` for an invalid URL; the message holds the server's field messages.

## clearWebhookUrl

Remove the webhook URL, also from operations in progress. Returns `true`.

```php
public function clearWebhookUrl(): bool
```

```php
$client->user->clearWebhookUrl();
```

## webhookLogs

Webhook delivery log, newest first, 10 per page.

```php
public function webhookLogs(int $page = 1): array
```

Returns a paginator (`current_page`, `data`, `last_page`, `total`, …) of `WebhookLog`: `['id', 'user_id', 'webhook_url', 'type', 'params', 'status', 'error', 'created_at', 'updated_at']` — `type` is `receiving_sms` or `rent_sms`, `status` is `success`, `fail` or `pending`, `params` the payload that was sent.

```php
foreach ($client->user->webhookLogs()['data'] as $log) {
    echo $log['created_at'], ' ', $log['status'], ' ', $log['error'] ?? '', "\n";
}
```
