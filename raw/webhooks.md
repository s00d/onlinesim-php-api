# Webhooks

> Receive SMS as HTTP callbacks and parse them with parseWebhook.

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

<table>
<thead>
  <tr>
    <th>
      Field
    </th>
    
    <th>
      Type
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
        webhook_type
      </code>
    </td>
    
    <td>
      <code>
        'receiving_sms'|'rent_sms'|null
      </code>
    </td>
    
    <td>
      Kind of operation.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        operation_id
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      <code>
        tzid
      </code>
      
       of the operation or rent.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        user_id
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      Account id.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        country_code
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      Country code of the number.
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
        ?string
      </code>
    </td>
    
    <td>
      Numbers: <code>
        +
      </code>
      
       and the full number (<code>
        +79001234567
      </code>
      
      ), or <code>
        hidden
      </code>
      
      . Rent: the full number in digits without <code>
        +
      </code>
      
       (<code>
        79001234567
      </code>
      
      ).
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        sender
      </code>
    </td>
    
    <td>
      <code>
        ?string
      </code>
    </td>
    
    <td>
      Sender of the SMS, e.g. <code>
        VK
      </code>
      
      .
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        message
      </code>
    </td>
    
    <td>
      <code>
        ?string
      </code>
    </td>
    
    <td>
      Full SMS text.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        code
      </code>
    </td>
    
    <td>
      <code>
        ?string
      </code>
    </td>
    
    <td>
      Code extracted from the SMS; <code>
        ''
      </code>
      
       when none was found.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        time_start
      </code>
    </td>
    
    <td>
      <code>
        ?string
      </code>
    </td>
    
    <td>
      Numbers: when the operation was ordered. Rent: when the SMS arrived. <code>
        YYYY-MM-DD HH:MM:SS
      </code>
      
      .
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        time_left
      </code>
    </td>
    
    <td>
      <code>
        ?int
      </code>
    </td>
    
    <td>
      Time left: seconds for numbers, minutes for rent.
    </td>
  </tr>
</tbody>
</table>
