# onlinesim-php-api

[![Packagist](https://img.shields.io/packagist/v/s00d/onlinesim-api.svg)](https://packagist.org/packages/s00d/onlinesim-api)
[![CI](https://github.com/s00d/onlinesim-php-api/actions/workflows/ci.yml/badge.svg)](https://github.com/s00d/onlinesim-php-api/actions/workflows/ci.yml)

PHP client for the [OnlineSim](https://onlinesim.io) API: SMS numbers, rent, account and free numbers.
PHP 8.1+, ext-curl, no runtime dependencies.

```bash
composer require s00d/onlinesim-api
```

```php
use s00d\OnlineSimApi\OnlineSim;

$client = new OnlineSim(apiKey: getenv('ONLINESIM_KEY') ?: null);

$balance = $client->user->balance();
echo 'available: ', $balance['balance'] - max($balance['zbalance'], 0), " USD\n";

$order = $client->numbers->getWithNumber('google', 44);
echo "enter {$order['number']} in the app\n";
echo 'code: ', $client->numbers->waitCode($order['tzid']), "\n";
```

## Testing

`MockOnlineSim` is a built-in offline fake of the API: test your integration without network, key or money.
More in the [testing guide](https://s00d.github.io/onlinesim-php-api/testing).

```php
use s00d\OnlineSimApi\Mock\MockOnlineSim;

$mock = new MockOnlineSim(balance: 10);
$client = $mock->client();

$tzid = $client->numbers->get('google', 44);
$mock->addSmsAfter($tzid, 'Your code: 123-456', 2); // arrives on the third poll
$code = $client->numbers->waitCode($tzid, interval: 0);

assert($code === '123456');
assert($mock->balance() === 9.8);
```

Documentation: **https://s00d.github.io/onlinesim-php-api/** · Changes: [CHANGELOG.md](CHANGELOG.md) · License: Apache-2.0
