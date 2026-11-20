# Free numbers

> Public numbers and their SMS — $client->free. No API key needed.

## list

Countries, the numbers of the selected country and the SMS of the selected number, in one call.

```php
public function list(?int $country = null, string|int|null $number = null, ?int $page = null, ?int $count = null): array
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
        ?int
      </code>
    </td>
    
    <td>
      <code>
        44
      </code>
      
       (server)
    </td>
    
    <td>
      Country code; an unknown one falls back to the first available.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        number
      </code>
    </td>
    
    <td>
      <code>
        string|int|null
      </code>
    </td>
    
    <td>
      first number
    </td>
    
    <td>
      Number <strong>
        without
      </strong>
      
       the country code, as the keys of <code>
        numbers
      </code>
      
      .
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        page
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      <code>
        1
      </code>
    </td>
    
    <td>
      Page of <code>
        messages
      </code>
      
      .
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        count
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      server default
    </td>
    
    <td>
      SMS per page, 10–500.
    </td>
  </tr>
</tbody>
</table>

`FreeList`:

- `countries`: `list<['country', 'country_text', 'country_original']>` (`country_text`/`country_original` may be `null`)
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
