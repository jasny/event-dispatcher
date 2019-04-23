<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher;

use Improved\IteratorPipeline\Pipeline;
use Jasny\ReflectionFactory\ReflectionFactory;
use LogicException;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;

/**
 * Event dispatcher.
 * @immutable
 */
class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var ReflectionFactory
     */
    protected $reflectionFactory;

    /**
     * @var array
     */
    protected $listeners = [];


    /**
     * ListenerProvider constructor.
     *
     * @param ReflectionFactory|null $reflectionFactory
     */
    public function __construct(ReflectionFactory $reflectionFactory = null)
    {
        $this->reflectionFactory = $reflectionFactory ?? new ReflectionFactory();
    }


    /**
     * Bind a handler for an event.
     *
     * @param callable $listener
     * @return static
     * @throws LogicException if listener is invalid
     */
    public function withListener(callable $listener): self
    {
        return $this->withListenerInNs('', $listener);
    }

    /**
     * Bind a handler for an event.
     *
     * @param string   $ns
     * @param callable $listener
     * @return static
     * @throws LogicException if listener is invalid
     */
    public function withListenerInNs(string $ns, callable $listener): self
    {
        if (strpos($ns, '*') !== false) {
            throw new \InvalidArgumentException("Invalid event ns '$ns': illegal character '*'");
        }

        $class = $this->getEventClassForListener($listener);

        $clone = clone $this;
        $clone->listeners[] = ['ns' => $ns, 'class' => $class, 'listener' => $listener];

        return $clone;
    }

    /**
     * Use reflection to get the event class from the first argument
     *
     * @param callable $listener
     * @return string
     * @throws LogicException
     */
    protected function getEventClassForListener(callable $listener): string
    {
        try {
            $reflFn = $this->reflectionFactory->reflectFunction($listener);
        } catch (ReflectionException $exception) {
            throw new LogicException("Invalid event listener: " . $exception->getMessage());
        }

        if ($reflFn->getNumberOfParameters() === 0) {
            throw new LogicException("Invalid event listener: No parameters defined");
        }

        [$reflParam] = $reflFn->getParameters();
        $class = $reflParam->getType();

        if ($class === null) {
            throw new LogicException(sprintf(
                'Invalid event listener: No type hint for parameter $%s',
                $reflParam->getName()
            ));
        }

        return $class->getName();
    }

    /**
     * Remove all listeners of the specified namespace.
     *
     * @param string $ns  Namespace, optionally with wildcards
     * @return static
     */
    public function withoutNs(string $ns): self
    {
        $listeners = Pipeline::with($this->listeners)
            ->filter(function ($trigger) use ($ns) {
                return !fnmatch($ns, $trigger['ns'], FNM_NOESCAPE)
                    && !fnmatch("$ns.*", $trigger['ns'], FNM_NOESCAPE);
            })
            ->values()
            ->toArray();

        if (count($listeners) === count($this->listeners)) {
            return $this;
        }

        $clone = clone $this;
        $clone->listeners = $listeners;

        return $clone;
    }


    /**
     * Get the relevant listeners for the given event.
     *
     * @param object $event
     * @return callable[]
     */
    public function getListenersForEvent(object $event): iterable
    {
        return Pipeline::with($this->listeners)
            ->filter(function ($trigger) use ($event) {
                return $this->reflectionFactory->isA($event, $trigger['class']);
            })
            ->column('listener')
            ->values()
            ->toArray();
    }
}
