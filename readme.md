# GHR

[packagist](https://packagist.org/packages/s00d/onlinesim-api)

Api for onlinesim

## Installation

Require this package in your `composer.json` or install it by running:
```
composer require s00d/onlinesim-api
```

## Basic Usage

```php
use App\Classes\Onlinesim\OnlineSimApi;
...
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
```
### Params

Name | Description
| ----------------- | ------------ |
apikey | your apikey from onlinesim.ru
locale | locale ru or en
dev_id | your dev_id, not require

### apis - balance
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
var_dump($request->balance());
```
### apis - GetNumber
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->numbers();
var_dump($getter->tariffs());
$res = $getter->get('3223');
var_dump($res);
$res = $getter->state();
// or
$res = $getter->state($res['tzid']);
var_dump($res);
...
$res = $getter->close($res['tzid']);

```

### apis - GetForward
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->forward();
$res = $getter->get();
var_dump($res);
$res = $getter->state();
// or 
$res = $getter->state($res['tzid']);
var_dump($res);
...
$res = $getter->close($res['tzid']);
```

### apis - GetProxy
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->proxy();
$res = $getter->get();
var_dump($res);
$res = $getter->state();
// or 
$res = $getter->state($res['tzid']);
var_dump($res);
```

### apis - GetRent
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->rent();
var_dump($getter->tariffs());
$res = $getter->get();
var_dump($res);
$res = $getter->state();
// or 
$res = $getter->state($res['tzid']);
var_dump($res);
...
$res = $getter->close($res['tzid']);
```

### apis - GetFree
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->free();
var_dump($getter->getList());
var_dump($getter->getPhoneList(7));
var_dump($getter->getMessageList(11111));
```

