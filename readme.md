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
var_dump($request->user()->balance()->toArray());
```

### apis - GetNumber

![Diagram](https://raw.githubusercontent.com/s00d/onlinesim-api/master/Diagrams/GetNumber.png "Workflow Diagram")

```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->numbers();
var_dump($getter->tariffs()->toArray());
$res = $getter->get('3223');
var_dump($res);
$res = $getter->state()->toArray();
// or
$res = $getter->stateOne($res->tzid)->toArray();

// next message
$getter->next($res->tzid)->toArray();
...
$res = $getter->state()->toArray();
var_dump($res);
...
$res = $getter->close($res->tzid)->toArray();

```

### apis - GetForward

![Diagram](https://raw.githubusercontent.com/s00d/onlinesim-api/master/Diagrams/GetForward.png "Workflow Diagram")

```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->forward([9111111111]);
$res = $getter->get(); // get new number
var_dump($res);
$res = $getter->state();
// or 
$res = $getter->stateOne($res->tzid)->toArray();
var_dump($res);
...
$res = $getter->forwardingList()->first();
...
$res = $getter->callList($res->tzid)->toArray();
...
$res = $getter->close($res->tzid)->toArray();
```

### apis - GetProxy

![Diagram](https://raw.githubusercontent.com/s00d/onlinesim-api/master/Diagrams/GetProxy.png "Workflow Diagram")

```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->proxy();
$res = $getter->get();
var_dump($res);
$res = $getter->state()->toArray();
// or 
$res = $getter->stateOne($res->tzid)->toArray();
var_dump($res);
```

### apis - GetRent

![Diagram](https://raw.githubusercontent.com/s00d/onlinesim-api/master/Diagrams/GetRent.png "Workflow Diagram")

```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->rent();
var_dump($getter->tariffs()->toArray());
$res = $getter->get();
var_dump($res);
$res = $getter->state()->toArray();
// or 
$res = $getter->stateOne($res->tzid)->toArray();
var_dump($res);
...
$res = $getter->close($res->tzid)->toArray();
```

### apis - GetFree

![Diagram](https://raw.githubusercontent.com/s00d/onlinesim-api/master/Diagrams/GetFree.png "Workflow Diagram")

```php
$request = new OnlineSimApi('apikey', 'locale', 'dev_id');
$getter = $request->free();
$country = $getter->countries();
var_dump($country->toArray());
$numbers = $getter->numbers($country->first()->country);
var_dump($numbers->toArray());
var_dump($getter->messages($numbers->first()->number)->toArray());
```


### Responses

All responses from the server are described in the folder Responses

### Bugs

If you have any problems, please create Issues here 
https://github.com/s00d/onlinesim-api/issues
