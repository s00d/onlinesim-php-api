# Onlinesim API

[packagist](https://packagist.org/packages/s00d/onlinesim-api)

Api for onlinesim

## Installation

Require this package in your `composer.json` or install it by running:
```
composer require s00d/onlinesim-api
```

## Basic Usage

```php
use s00d\OnlineSimApi\OnlineSimApi;
...
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
```
### Params

Name | Description
| ----------------- | ------------ |
apikey | your apikey from onlinesim.ru
locale | locale ru or en
dev_id | your dev_id, not require

### apis - GetUser
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
var_dump($request->user()->balance());
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
$res = $getter->stateOne($res['tzid']);
var_dump($res);
...
$res = $getter->close($res['tzid']);

```

### apis - GetForward
```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->forward([9111111111]);
$res = $getter->get(); // get new number
var_dump($res);
$res = $getter->state();
// or 
$res = $getter->stateOne($res['tzid']);
var_dump($res);
...
$res = $getter->forwardingList($res['tzid']);
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
$res = $getter->stateOne($res['tzid']);
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
$res = $getter->stateOne($res['tzid']);
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
var_dump($getter->getMessageList(91111...));
```


### Responses

All responses from the server are described in the folder Responses

### Bugs

If you have any problems, please create Issues here 
https://github.com/s00d/onlinesim-api/issues
