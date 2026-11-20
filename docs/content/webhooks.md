---
title: Webhooks
description: Receive SMS as HTTP callbacks and parse them with parseWebhook.
navigation:
  order: 5
---

Set a URL with [`$client->user->setWebhookUrl`](/user#setwebhookurl). For each SMS the server POSTs a JSON body to it: `webhook_type` `receiving_sms` for numbers, `rent_sms` for rents. Delivery attempts are listed by [`$client->user->webhookLogs`](/user#webhooklogs).

## parseWebhook

Parse an inbound webhook body from a JSON string or an already decoded array. A namespaced function, not a client method.

```php
function parseWebhook(string|array $payload): array
```

Lenient: numeric fields may arrive as strings, missing or malformed fields become `null`, unknown fields are dropped. It throws `OnlineSimError` only when the input is not a JSON object.

```php
use function s00d\OnlineSimApi\parseWebhook;

// webhook.php, the URL you passed to setWebhookUrl
$hook = parseWebhook((string) file_get_contents('php://input'));
if ($hook['webhook_type'] === 'receiving_sms') {
    error_log("operation {$hook['operation_id']}: code {$hook['code']}");
}
echo 'ok';
```

## Payload fields

| Field | Type | Description |
|---|---|---|
| `webhook_type` | `'receiving_sms'\|'rent_sms'\|null` | Kind of operation. |
| `operation_id` | `?int` | `tzid` of the operation or rent. |
| `user_id` | `?int` | Account id. |
| `country_code` | `?int` | Country code of the number. |
| `number` | `?string` | Numbers: `+` and the full number (`+79001234567`), or `hidden`. Rent: the full number in digits without `+` (`79001234567`). |
| `sender` | `?string` | Sender of the SMS, e.g. `VK`. |
| `message` | `?string` | Full SMS text. |
| `code` | `?string` | Code extracted from the SMS; `''` when none was found. |
| `time_start` | `?string` | Numbers: when the operation was ordered. Rent: when the SMS arrived. `YYYY-MM-DD HH:MM:SS`. |
| `time_left` | `?int` | Time left: seconds for numbers, minutes for rent. |
