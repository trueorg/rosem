<?php

namespace TrueCode\Container;

use TrueCode\Container\Definition\{
    Aggregate\AggregatedDefinitionInterface,
    ClassDefinition,
    DefinitionInterface,
    FunctionDefinition,
    Proxy\DefinitionProxyInterface,
    SharedDefinition,
    SharedDefinitionInterface
};

class Container extends AbstractContainer
{
    use ExtractorTrait;

    /**
     * Container constructor.
     */
    public function __construct()
    {
        AbstractFacade::registerContainer($this);
    }

    public function bind(string $abstract, $concrete = null, array ...$args) : DefinitionProxyInterface
    {
        return new Definition\Proxy\DefinitionProxy($this, $abstract, $concrete ?: $abstract, $args);
    }

    /**
     * @param string  $abstract
     * @param null    $concrete
     * @param array[] ...$args
     *
     * @return DefinitionInterface
     * @throws \ReflectionException
     */
    public function forceBind(string $abstract, $concrete = null, array ...$args) : DefinitionInterface
    {
        if (! $concrete) {
            $concrete = $abstract;
        }

        if (is_string($concrete) && class_exists($concrete)) {
            return $this->forceBindClass($abstract, $concrete, ...$args);
        }

        if (is_callable($concrete)) {
            return $this->forceBindFunction($abstract, $concrete, reset($args) ?: []);
        }

        return new SharedDefinition($this, $abstract, $concrete, reset($args) ?: []);
    }

    /**
     * @param string  $abstract
     * @param null    $concrete
     * @param array[] ...$args
     *
     * @return DefinitionInterface
     * @throws \ReflectionException
     */
    protected function forceBindClass(string $abstract, $concrete = null, array ...$args) : DefinitionInterface
    {
        $binding = new ClassDefinition($this, $abstract, $concrete, $this->extractFirst($args));

        return method_exists($concrete, '__invoke')
            ? $binding->withMethodCall('__invoke', $args ?: [])
            : $binding;
    }

    /**
     * @param string $abstract
     * @param null   $concrete
     * @param array  $args
     *
     * @return DefinitionInterface
     * @throws \ReflectionException
     */
    protected function forceBindFunction(string $abstract, $concrete = null, array $args = []) : DefinitionInterface
    {
        return new FunctionDefinition($this, $abstract, $concrete, $args);
    }

    /**
     * Wrap function under makeInstance.
     *
     * @param string  $abstract
     * @param array[] ...$args
     *
     * @return mixed
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function make(string $abstract, array ...$args)
    {
        if (! $binding = $this->find($abstract)) {
            if ($this->delegate) {
                return $this->delegate->make($abstract, ...$args);
            }

            throw new Exception\NotFoundException("$abstract binding not found.");
        }

        return $binding->make(...$args);
    }

    public function invoke($abstract, array ...$args)
    {
        // TODO: improve - make like call method
        if (! $binding = $this->find($abstract)) {
            if ($this->delegate) {
                return $this->delegate->invoke($abstract, ...$args);
            }

            throw new Exception\NotFoundException("$abstract binding not found.");
        }

        return $binding instanceof AggregatedDefinitionInterface
            ? $binding->invoke(...$args)
            : $binding->make(...$args);
    }

    public function call($callable, array ...$args)
    {
        if (is_string($callable)) {
            if (is_callable($callable)) {
                if ($binding = $this->find($callable)) {
                    return $binding instanceof AggregatedDefinitionInterface
                        ? $binding->call(...$args)
                        : $binding->make(...$args);
                } elseif ($this->delegate) {
                    return $this->delegate->call($callable, ...$args);
                }

                // error: not found
            } else {
                // is a $callable "classname::method"
                if (strpos($callable, '::') !== false) {
                    [$class, $method] = explode('::', $callable, 2);

                    if ($binding = $this->find($class)) {
                        return $binding->withMethodCall($method)->call(...$args);
                    } elseif ($this->delegate) {
                        return $this->delegate->call([$class, $method], ...$args);
                    }

                    // error
                } elseif ($binding = $this->find($callable)) {
                    return $binding instanceof AggregatedDefinitionInterface
                        ? $binding->call(...$args)
                        : $binding->make(...$args);
                } elseif ($this->delegate) {
                    return $this->delegate->call($callable, ...$args);
                }

                // error: not found
            }
        } elseif (is_array($callable)) {
            if (is_string(next($callable))) {
                // when all array items are strings
                if (is_string(reset($callable))) {
                    if ($binding = $this->find(reset($callable))) {
                        return $binding->withMethodCall(next($callable))->call(...$args);
                    } elseif ($this->delegate) {
                        return $this->delegate->call($callable, ...$args);
                    }

                    // error: not found
                }
                // when first array item is an object and second is a string
                elseif (is_object($instance = reset($callable))) {
                    return $this->instance(get_class($instance), $instance)
                        ->withMethodCall(next($callable))->call(...$args);
                }

                // error: invalid array items
            }

            // error: call() expects parameter 1 to be a valid callback, second array member is not a valid method
        }

        // error: invalid argument
    }

    /**
     * @param string $abstract
     *
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function get($abstract)
    {
        return $this->invoke($abstract);
    }

    /**
     * Register an alias for existing interface.
     *
     * @param string $alias
     * @param string $abstract
     */
    public function alias(string $abstract, string $alias) : void
    {
        $this->bindings[$alias] = &$this->bindings[$abstract];
    }

    public function instance(string $abstract, $instance) : DefinitionInterface
    {
        return new SharedDefinition($this, $abstract, $instance);
    }

    public function share(string $abstract, $concrete = null, array ...$args) : DefinitionProxyInterface
    {
        return new Definition\Proxy\SharedDefinitionProxy($this, $abstract, $concrete ?: $abstract, $args);
    }

    /**
     * Determine if a given type is shared.
     *
     * @param string $abstract
     *
     * @return bool
     */
    public function isShared(string $abstract) : bool
    {
        return ($this->delegate && ! $this->has($abstract))
            ? $this->delegate->isShared($abstract)
            : $this->bindings[$abstract] instanceof SharedDefinitionInterface;
    }
}
