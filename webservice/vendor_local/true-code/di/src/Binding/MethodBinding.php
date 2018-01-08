<?php

namespace True\DI\Binding;

use ReflectionMethod;
use SplFixedArray;
use True\DI\AbstractContainer;

class MethodBinding implements DependentBindingInterface
{
    use ReflectedBuildTrait;

    /**
     * @var string
     */
    protected $concrete;

    /**
     * @var array
     */
    protected $args;

    public function __construct(AbstractContainer $container, $context, $concrete, array $args = [])
    {
        $this->container = $container;
        $this->concrete = $concrete;
        $this->args = $args;
        $this->reflector = new ReflectionMethod($context, $this->concrete);

        if ($params = SplFixedArray::fromArray($this->reflector->getParameters())) {
            $this->stack = $this->getStack($params);
        }
    }

    public function make($context, array $args = [])
    {
        return $this->reflector->invokeArgs($context, $this->build($this->stack, $args ?: $this->args));
    }
}