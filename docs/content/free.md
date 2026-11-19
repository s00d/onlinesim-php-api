---
title: Free numbers
description: Public numbers and their SMS — $client->free. No API key needed.
navigation:
  order: 4
---

## list

Countries, the numbers of the selected country and the SMS of the selected number, in one call.

```php
public function list(?int $country = null, string|int|null $number = null, ?int $page = null, ?int $count = null): array
```

| Param | Type | Default | Description |
|---|---|---|---|
| `country` | `?int` | `44` (server) | Country code; an unknown one falls back to the first available. |
| `number` | `string\|int\|null` | first number | Number **without** the country code, as the keys of `numbers`. |
| `page` | `?int` | `1` | Page of `messages`. |
| `count` | `?int` | server default | SMS per page, 10–500. |

`FreeList`:

- `countries`: `list<['country', 'country_text', 'country_original']>`
- `numbers`: `['country', 'country_original', 'full_number', 'data_humans', 'is_archive']` keyed by the short number (PHP turns numeric keys into `int`)
- `messages`: a paginator of `['id', 'text', 'in_number', 'my_number', 'code', 'created_at', 'data_humans']` plus `number` and `country`; `code` is `-` when no code was found

```php
$numbers = $client->free->list(country: 44)['numbers'];
$short = array_key_first($numbers);
if ($short !== null) {
    $messages = $client->free->list(country: 44, number: $short, count: 20)['messages'];
    foreach ($messages['data'] as $sms) {
        echo $sms['in_number'], ' ', $sms['code'], ' ', $sms['text'], "\n";
    }
}
```
