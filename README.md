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

The PSR only requires determining events based on their class name, any other method is optional. Libraries should only
depend on the specification and not on the implementation, therefore each event type must have it's own class.

Installation
---

    composer require jasny/event-dispatcher

Usage
---

#### 1. Define your own event classes

```php
namespace App\Event;

/**
 * Base class for all events in this application.
 */
abstract class Base
{
    /** @var object */
    protected $emitter;

    /** @var mixed */
    protected $payload;

    public function __construct(object $emitter, $payload = null)
    {
        $this->emitter = $emitter;
        $this->payload = $payload;
    }
    
    final public function getEmitter(): object
    {
        return $this->emitter;
    }

    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload()
    {
        return $this->payload;
    }
}

/**
 * Called before an entity is saved to the database.
 */
class BeforeSave extends Base
{}

/**
 * Called when an entity is casted to json.
 */
class ToJson extends Base
{} 
```

#### 2. Create listeners

```php
use App\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->withListener(function(Event\BeforeSave $event): void {
        $entity = $event->getEmitter();
        $payload = $event->getPayload();
        
        $payload['bio'] = $payload['bio'] ?? ($entity->name . " just arrived");
        $event->setPayload($payload);
    })
    ->withListener(function(Event\ToJson $event): void {
        $payload = $event->getPayload();
        
        unset($payload['password']);
        $event->setPayload($payload);
    });
```

The provider will use the type hint of the first argument of the lister to determine if the listener applies to the
given event.  

Listeners are executed in the order they're registered to the provider. It's not possible to prepend existing
listeners.

#### 3. Create the dispatcher

```php
$dispatcher = new EventDispatcher($listener);
```

#### 4. Dispatch an event

Typically a subject will hold its own dispatcher and trigger events.

```php
use App\Event;
use Jasny\EventDispatcher\EventDispatcher;

class Foo implements JsonSerializable
{
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    // ...
    
    public function jsonSerialize()
    {
        $payload = get_object_vars($this);
    
        return $this->eventDispatcher->dispatch(new Event\ToJson($this, $payload));
    }
}
```

### Add listener

`ListenerProvider` and `EventDispatcher` are immutable services. Methods `withListener` and `withListenerProvider` resp
will create a modified copy of each service.

```php
use App\Event;

$newListener = $dispatcher->getListener()
    ->off(function(Event\BeforeSave $event): void {
        $payload = $event->getPayload();
       
        $payload['bio'] = strtr($payload['bio'], $payload['email'], '***@***.***');
        $event->setPayload($payload);
    });

$newDispatcher = $dispatcher->withListenerProvider($newListener);
```

### Stoppable events

The event must implement the `StoppableEventInterface` of PSR-14.

```php
namespace App\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Called before an entity is saved to the database.
 */
class BeforeSave implememnts StoppableEventInterface
{
    // ...

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
```


```php
use App\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->on(function(Event\BeforeSave $event): void {
        $entity = $event->getEmitter();
        
        if (!$entity->isReady()) {
            $event->stopPropagation();
        }
    });
    
$dispatcher = new EventDispatcher($listener);
```

### Listener namespace

Listeners may be registered to the provider under a namespace.

```php
use App\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;

$listener = (new ListenerProvider)
    ->withListenerInNs('censor', function(Event\BeforeSave $event): void {
        $payload = $event->getPayload();
        
        $payload['bio'] = strtr($payload['bio'], $payload['email'], '***@***.***');
        $event->setPayload($payload);
    });
    ->withListenerInNs('censor.json', function(Event $event): void {
        $payload = $event->getPayload();
        
        unset($payload['password']);
        $event->setPayload($payload);
    });
```

This can be used to remove all listeners within the namespace and all subnamespaces.

```php
$newListeners = $dispatcher->getListenerProvider()->withoutNs('censor');
```

_This example removes both the listener in the `censor` and `censor.json` namespace._

#### Namespace wildcard

You may use a wildcard to specify all subnamespaces regardless of the parent 

```php
$newListeners = $dispatcher->getListenerProvider()->withoutNs('*.json');
```

_This example removes the listener in the `censor.json` namespace._
