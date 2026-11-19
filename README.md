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
echo 'available: ', $balance['balance'] - $balance['zbalance'], " USD\n";

$order = $client->numbers->getWithNumber('google', 44);
echo "enter {$order['number']} in the app\n";
echo 'code: ', $client->numbers->waitCode($order['tzid']), "\n";
```

Documentation: **https://s00d.github.io/onlinesim-php-api/** · Changes: [CHANGELOG.md](CHANGELOG.md) · License: Apache-2.0
