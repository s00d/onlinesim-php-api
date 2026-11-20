# Rent

> Rent a number for days and receive every SMS sent to it — $client->rent.

A rent keeps a number for `days` days. Its SMS come in `messages`, newest first.

`get` reserves the price (it shows in `zbalance`): the rent starts with `status` `0` and `checked` `false` and waits up to 20 minutes for its first SMS. The first SMS that is not a `notify` notice confirms it (`status` `1`) and charges the price; without one the rent closes and the reservation is released. Some countries (`confirm` in `tariffs`, e.g. US numbers) are confirmed and charged at once.

`RentItem`: `['tzid', 'status', 'number', 'country', 'sum', 'days', 'hours', 'time', 'time_seconds'?, 'extension', 'extend', 'messages', 'start_time', 'stop_time', 'day_extend'?, …]` —
`sum` is the cost in USD incl. extensions (a `float`; the server sends a number, or a decimal string in `state`), `time` minutes left, `time_seconds` seconds left,
`extension` `0` not extendable / `1` extendable / `2` auto-extension, `extend` maps days to the extension price (`[]` when not extendable),
`messages` is `list<RentSms>`: `['id', 'service', 'text', 'code', 'created_at']` — `code` is `''` when no code was found and the integer `0` for a `notify` SMS (a prohibited sender); it is kept as sent (`string|int`).

## get

Rent a number. The price is reserved; the first SMS charges it.

```php
public function get(int $country = 7, int $days = 1, bool $extension = true): array
```

<table>
<thead>
  <tr>
    <th>
      Param
    </th>
    
    <th>
      Type
    </th>
    
    <th>
      Default
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        country
      </code>
    </td>
    
    <td>
      <code>
        int
      </code>
    </td>
    
    <td>
      <code>
        7
      </code>
    </td>
    
    <td>
      Country code.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        days
      </code>
    </td>
    
    <td>
      <code>
        int
      </code>
    </td>
    
    <td>
      <code>
        1
      </code>
    </td>
    
    <td>
      One of the tariff's <code>
        days
      </code>
      
       keys.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        extension
      </code>
    </td>
    
    <td>
      <code>
        bool
      </code>
    </td>
    
    <td>
      <code>
        true
      </code>
    </td>
    
    <td>
      <code>
        false
      </code>
      
       makes the rent non-extendable for good.
    </td>
  </tr>
</tbody>
</table>

```php
$rent = $client->rent->get(7, days: 7);
echo $rent['tzid'], ' ', $rent['number'], ' ', $rent['sum'];
```

Errors: `NoNumberError`; `ApiError` `UNDEFINED_DAYS`, `UNDEFINED_COUNTRY`, `WARNING_LOW_BALANCE`, `INTERVAL_CONCURRENT_REQUESTS_ERROR`, `TRY_AGAIN_LATER`.

## state

All rents of the account.

```php
public function state(): array
```

```php
foreach ($client->rent->state() as $rent) {
    echo $rent['number'], ' ', $rent['messages'][0]['code'] ?? '-', "\n";
}
```

## stateOne

One rent.

```php
public function stateOne(int $tzid): array
```

```php
$messages = $client->rent->stateOne($tzid)['messages'];
```

Errors: `ApiError` `ERROR_NO_OPERATIONS` for an unknown tzid.

## extend

Extend a rent by `days` days and charge the balance at once. Only confirmed rents (`status` `1` or `4`) created with `extension: true`.

```php
public function extend(int $tzid, int $days = 1): array
```

```php
$rent = $client->rent->extend($tzid, days: 3);
echo $rent['stop_time'];
```

Errors: `ApiError` `TRY_AGAIN_LATER` (unknown tzid, not confirmed yet or closed, not extendable, unknown period, a repeat within 10 s), `WARNING_LOW_BALANCE`.

## close

Return the number before the period ends. A rent that is not confirmed yet (`status` `0`) and has no SMS can be closed only after 120 s; then, or with only `notify` SMS, the reservation is released. With a real SMS the rent is confirmed (charged) and closed. A confirmed rent closes without a refund. US numbers cannot be closed early. Returns `true`.

```php
public function close(int $tzid): bool
```

```php
$client->rent->close($tzid);
```

Errors: `ApiError` `ERROR_NO_OPERATIONS`, `NO_COMPLETE_TZID`, `NO_CLOSE_RENT_US`, `TRY_AGAIN_LATER`.

## tariffs

Rent prices of every country, keyed by country code. No API key needed.

```php
public function tariffs(): array
```

Returns `array<int, RentTariff>`; `RentTariff`: `['code', 'name', 'enabled', 'new', 'position', 'extend', 'confirm'?, 'days', 'count', 'ext_counts'?]` — `days` maps rent days to the price in USD, `count` to the numbers available.

```php
$tariffs = $client->rent->tariffs();
```

## tariffsOne

Rent prices of one country.

```php
public function tariffsOne(int $country = 7): array
```

```php
$days = $client->rent->tariffsOne(7)['days'];
echo $days[7] ?? '-'; // price of 7 days
```

Errors: `ApiError` `NO_COUNTRY` for a country without rent.
