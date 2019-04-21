<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Event dispatcher.
 * @immutable
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var ListenerProviderInterface
     */
    protected $listenerProvider;


    /**
     * EventDispatcher constructor.
     *
     * @param ListenerProviderInterface $listenerProvider
     */
    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }


    /**
     * Get the listener provider used by this dispatcher
     *
     * @return ListenerProviderInterface
     */
    public function getListenerProvider(): ListenerProviderInterface
    {
        return $this->listenerProvider;
    }

    /**
     * Get a dispatcher with a different/modified listener provider.
     *
     * @param ListenerProviderInterface $listenerProvider
     * @return static
     */
    public function withListenerProvider(ListenerProviderInterface $listenerProvider): self
    {
        if ($this->listenerProvider === $listenerProvider) {
            return $this;
        }

        $clone = clone $this;
        $clone->listenerProvider = $listenerProvider;

        return $clone;
    }


    /**
     * Dispatch an event.
     *
     * @param object $event
     * @return object  The event that was passed, now modified by the listeners.
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
