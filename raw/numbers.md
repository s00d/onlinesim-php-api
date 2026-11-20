# Numbers

> Order a number, read its SMS, close it — $client->numbers.

An operation (`tzid`) is one ordered number. Order it with `get`/`getWithNumber`, read the SMS with `stateOne` or `waitCode`, finish it with `close` (or `ban`). Countries are phone codes (`7`, `44`, …), services are slugs (`google`, `vkcom`, …) as returned by `tariffs`.

## price

Price of one number, USD; `null` when the country has no price for the service.

```php
public function price(string $service, int $country = 7): ?float
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
        service
      </code>
    </td>
    
    <td>
      <code>
        string
      </code>
    </td>
    
    <td>
      —
    </td>
    
    <td>
      Service slug.
    </td>
  </tr>
  
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
</tbody>
</table>

```php
$usd = $client->numbers->price('google', 44);
```

Errors: `ApiError` `ERROR_NO_SERVICE`, `UNDEFINED_COUNTRY`.

## get

Order a number; returns the operation id. Freezes the price on the balance.

```php
public function get(string $service, int $country = 7, array $reject = []): int
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
        service
      </code>
    </td>
    
    <td>
      <code>
        string
      </code>
    </td>
    
    <td>
      —
    </td>
    
    <td>
      Service slug.
    </td>
  </tr>
  
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
        reject
      </code>
    </td>
    
    <td>
      <code>
        list<string|int>
      </code>
    </td>
    
    <td>
      profile list
    </td>
    
    <td>
      Number prefixes to skip, e.g. <code>
        ['4470']
      </code>
      
      .
    </td>
  </tr>
</tbody>
</table>

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
        messageToCode
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
        msg
      </code>
      
       is the code only; <code>
        false
      </code>
      
       — the full SMS text.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        orderBy
      </code>
    </td>
    
    <td>
      <code>
        OrderBy
      </code>
    </td>
    
    <td>
      <code>
        OrderBy::Asc
      </code>
    </td>
    
    <td>
      Order of operations (<code>
        OrderBy::Asc
      </code>
      
      , <code>
        OrderBy::Desc
      </code>
      
      ).
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        msgList
      </code>
    </td>
    
    <td>
      <code>
        bool
      </code>
    </td>
    
    <td>
      <code>
        false
      </code>
    </td>
    
    <td>
      <code>
        msg
      </code>
      
       is a list of all SMS (<code>
        OperationSms[]
      </code>
      
      ).
    </td>
  </tr>
</tbody>
</table>

Returns `list<Operation>`; `Operation`: `['tzid', 'response', 'number'?, 'service'?, 'country'?, 'sum'?, 'time'?, 'msg'?, 'guard_interval_remaining_seconds'?, …]`.
`response` is the state (absent when no number could be issued, so read it with `?? null`): `TZ_INPOOL` (number being assigned), `TZ_NUM_WAIT` (waiting for SMS), `TZ_NUM_ANSWER` (SMS arrived), `TZ_OVER_OK` (finished), `ERROR_NO_ITEMS` (failed). `msg` is absent until the first SMS. The server caches answers for a few seconds.

```php
use s00d\OnlineSimApi\OrderBy;

foreach ($client->numbers->state(orderBy: OrderBy::Desc) as $op) {
    echo $op['tzid'], ' ', $op['response'] ?? '-', ' ', $op['msg'] ?? '-', "\n";
}
```

## stateOne

One operation.

```php
public function stateOne(int $tzid, bool $messageToCode = true, bool $msgList = false): array
```

```php
$op = $client->numbers->stateOne($tzid);
if (($op['response'] ?? null) === 'TZ_NUM_ANSWER') {
    echo $op['msg'];
}
```

Errors: `ApiError` `ERROR_NO_OPERATIONS` when the operation does not exist (any more).

## waitCode

Poll the operation until a code arrives, then close it and return the code.

```php
public function waitCode(int $tzid, float $interval = 5.0, float $timeout = 600.0, bool $keepOpen = false, bool $fullMessage = false, ?string $since = null, ?callable $sleep = null, ?callable $now = null): string
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
        interval
      </code>
    </td>
    
    <td>
      <code>
        float
      </code>
    </td>
    
    <td>
      <code>
        5.0
      </code>
    </td>
    
    <td>
      Poll interval, seconds.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        timeout
      </code>
    </td>
    
    <td>
      <code>
        float
      </code>
    </td>
    
    <td>
      <code>
        600.0
      </code>
    </td>
    
    <td>
      Give up after, seconds.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        keepOpen
      </code>
    </td>
    
    <td>
      <code>
        bool
      </code>
    </td>
    
    <td>
      <code>
        false
      </code>
    </td>
    
    <td>
      Call <code>
        next($tzid)
      </code>
      
       instead of <code>
        close($tzid)
      </code>
      
      , to wait for another SMS.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        fullMessage
      </code>
    </td>
    
    <td>
      <code>
        bool
      </code>
    </td>
    
    <td>
      <code>
        false
      </code>
    </td>
    
    <td>
      Return the full SMS text instead of the code.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        since
      </code>
    </td>
    
    <td>
      <code>
        ?string
      </code>
    </td>
    
    <td>
      <code>
        null
      </code>
    </td>
    
    <td>
      Ignore this value (the previous code).
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        sleep
      </code>
      
      , <code>
        now
      </code>
    </td>
    
    <td>
      callables
    </td>
    
    <td>
      <code>
        usleep
      </code>
      
      , <code>
        hrtime
      </code>
    </td>
    
    <td>
      <code>
        sleep(float $seconds)
      </code>
      
       and a monotonic <code>
        now(): float
      </code>
      
       in seconds; injectable for tests.
    </td>
  </tr>
</tbody>
</table>

Transient errors are retried until the timeout: `TransportError`, and `ApiError` `TRY_AGAIN_LATER` (the HTTP 429 rate limit), `ERROR_UNDEFINED` and `INTERVAL_CONCURRENT_REQUESTS_ERROR`. Any other `ApiError` is thrown at once.

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

Errors: `ApiError` `ERROR_WRONG_TZID` (not an operation of your account), `ERROR_NO_TZID`.

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

Errors: as `close`; `ApiError` with code `"0"` when the operation already has an SMS — nothing is changed then.

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
