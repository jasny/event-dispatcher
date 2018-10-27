Jasny Event Dispatcher
===

[![Build Status](https://travis-ci.org/jasny/event-dispatcher.svg?branch=master)](https://travis-ci.org/jasny/event-dispatcher)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/event-dispatcher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/event-dispatcher/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/event-dispatcher/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/event-dispatcher/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/event-dispatcher.svg)](https://packagist.org/packages/jasny/event-dispatcher)
[![Packagist License](https://img.shields.io/packagist/l/jasny/event-dispatcher.svg)](https://packagist.org/packages/jasny/event-dispatcher)

Event Dispatching is a common and well-tested mechanism to allow developers to inject logic into an application easily
and consistently.

This library will most likely change once PSR-14 crystallizes.

_All objects are immutable._

Installation
---

    composer require jasny/event-dispatcher

Usage
---

```php
use Jasny\EventDispatcher;
use function Jasny\array_without;

$dispatcher = (new EventDispatcher)
    ->on('before-save', function(object $subject, $payload) {
        $payload['bio'] = $payload['bio'] ?? $subject->name . " just arrived";
        
        return $payload;
    })
    ->on('json', function(object $subject, $payload) {
        return array_without($payload, ['password']);
    });
```

The new payload must be return and will be passed to the next handler and eventually becomes the result of the trigger.
If a callback doesn't modify the payload, it MUST still return it.

Typically a subject will hold its own dispatcher and trigger events.

```php
use function Jasny\object_get_properties;

class Foo implements JsonSerializable
{
    // ...
    
    public function jsonSerialize()
    {
        $data = object_get_properties($this);
    
        return $this->eventDispatcher->trigger('json', $data);
    }
}
```

If needed you can remove all handlers of an event.

```php
$newDispatcher = $dispatcher->off('before-save');
```

### Event namespace

Event names may use a namespace, similar to events in `jQuery`.

```php
use Jasny\EventDispatcher;
use function Jasny\array_without;

$dispatcher = (new EventDispatcher)
    ->on('before-save.censor', function(object $subject, $payload) {
        $payload['bio'] = strtr($payload['bio'], $payload['email'], '***@***.***');
        
        return $payload;
    });
    ->on('json.censor', function(object $subject, $payload) {
        return array_without($payload, ['password']);
    });
```

This can be used to remove all handlers within the namespace

```php
$newDispatcher = $dispacher->off('*.censor');
```

The `.*` is not required as suffix for `off`. The following call would remove `before-save.censor`

```php
$newDispatcher = $dispacher->off('before-save');
```
