Jasny Event Dispatcher
===

[![Build Status](https://travis-ci.org/jasny/event-dispatcher.svg?branch=master)](https://travis-ci.org/jasny/event-dispatcher)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/event-dispatcher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/event-dispatcher/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/event-dispatcher/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/event-dispatcher/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/event-dispatcher.svg)](https://packagist.org/packages/jasny/event-dispatcher)
[![Packagist License](https://img.shields.io/packagist/l/jasny/event-dispatcher.svg)](https://packagist.org/packages/jasny/event-dispatcher)

A [PSR-14](https://www.php-fig.org/psr/psr-14/) compatible event dispatcher that's easy to use.

Event dispatching is a common and well-tested mechanism to allow developers to inject logic into an application easily
and consistently.

Installation
---

    composer require jasny/event-dispatcher

Usage
---

```php
use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->on('before-save', function(Event $event): void {
        $subject = $event->getSubject();
        $payload = $event->getPayload();
        
        $payload['bio'] = $payload['bio'] ?? ($subject->name . " just arrived");
        $event->setPayload($payload);
    })
    ->on('json', function(Event $event, $subject, $payload): void {
        $payload = $event->getPayload();
        
        unset($payload['password']);
        $event->setPayload($payload);
    });
    
$dispatcher = new EventDispatcher($listener);
```

Listeners are executed in the order they're registered to the provider. It's not possible to prepend existing
listeners. 

Typically a subject will hold its own dispatcher and trigger events.

```php
use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\EventDispatcher;
use function Jasny\object_get_properties;

class Foo implements JsonSerializable
{
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    // ...
    
    public function jsonSerialize()
    {
        $payload = object_get_properties($this);
    
        return $this->eventDispatcher->dispatch(new Event('json', $this, $payload));
    }
}
```

_`Event` is a mutable object that's passed from listener to listener. All other objects are immutable services._

### Remove listener

If needed you can remove all listeners for an event with the `off()` method.

```php
$newListener = $dispatcher->getListener()->off('before-save');
$newDispatcher = $dispatcher->withListener($newListener);
```

### Stoppable events

```php
use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->on('before-save', function(Event $event): void {
        $subject = $event->getSubject();
        
        if (!$subject->isReady()) {
            $event->stopPropagation();
        }
    });
    
$dispatcher = new EventDispatcher($listener);
```

### Event namespace

Event names may use a namespace, similar to events in `jQuery`.

```php
use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->on('before-save.censor', function(Event $event): void {
        $subject = $event->getSubject();
        $payload = $event->getPayload();
        
        $payload['bio'] = strtr($payload['bio'], $payload['email'], '***@***.***');
        $event->setPayload($payload);
    });
    ->on('json.censor', function(Event $event): void {
        $payload = $event->getPayload();
        
        unset($payload['password']);
        $event->setPayload($payload);
    });
```

This can be used to remove all listeners within the namespace

```php
$newListeners = $dispatcher->getListenerProvider()->off('*.censor');
```

The `.*` is not required as suffix for `off`. The following call would remove `before-save.censor`

```php
$newListeners = $dispatcher->getListenerProvider()->off('before-save');
```

### Custom event classes

Alternatively you can use the class name of the event class for registering listeners. This allows you to use custom
event classes. It's not required for custom event classes to extend the `Event` class.

```php
use App\FooSaveEvent;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->on(FooSaveEvent::class, function(FooSaveEvent $event): void {
        // ...
    });
```

_It's not possible to use match both on event name and class name._
