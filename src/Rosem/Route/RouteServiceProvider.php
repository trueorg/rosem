<?php

namespace Rosem\Route;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Rosem\Psr\Container\ServiceProviderInterface;
use Rosem\Psr\Http\Server\MiddlewareCollectorInterface;
use Rosem\Psr\Route\{
    RouteCollectorInterface,
    RouteDispatcherInterface};
use Rosem\Route\DataGenerator\MarkBasedDataGenerator;
use Rosem\Route\Dispatcher\MarkBasedDispatcher;
use Rosem\Route\Http\Server\{
    HandleRequestMiddleware,
    RouteMiddleware};

class RouteServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns a list of all container entries registered by this service provider.
     *
     * @return callable[]
     * @throws \InvalidArgumentException
     */
    public function getFactories(): array
    {
        return [
            RouteCollectorInterface::class => [static::class, 'createRouteCollector'],
            RouteDispatcherInterface::class => [static::class, 'createRouteDispatcher'],
            RouteMiddleware::class => [static::class, 'createRouteMiddleware'],
            HandleRequestMiddleware::class => [static::class, 'createHandleRequestMiddleware'],
        ];
    }

    /**
     * Returns a list of all container entries extended by this service provider.
     *
     * @return callable[]
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getExtensions(): array
    {
        return [
            MiddlewareCollectorInterface::class => function (
                ContainerInterface $container,
                MiddlewareCollectorInterface $middlewareCollector
            ) {
                $middlewareCollector->use(RouteMiddleware::class);
                $middlewareCollector->use(HandleRequestMiddleware::class);
            },
        ];
    }

    /**
     * @param ContainerInterface $container
     *
     * @return RouteCollectorInterface
     * @throws \InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createRouteCollector(ContainerInterface $container): RouteCollectorInterface
    {
        return new Collector(new Compiler(new Parser()), new MarkBasedDataGenerator());
    }

    /**
     * @param ContainerInterface $container
     *
     * @return RouteDispatcherInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createRouteDispatcher(ContainerInterface $container): RouteDispatcherInterface
    {
        /** @var Collector $collector */
        $collector = $container->get(RouteCollectorInterface::class);

        return new Dispatcher(
            $collector->getStaticRouteMap(),
            $collector->getVariableRouteMap(),
            new MarkBasedDispatcher()
        );
    }

    public function createRouteMiddleware(ContainerInterface $container): RouteMiddleware
    {
        return new RouteMiddleware(
            $container->get(RouteDispatcherInterface::class),
            $container->get(ResponseFactoryInterface::class)
        );
    }

    public function createHandleRequestMiddleware(ContainerInterface $container): HandleRequestMiddleware
    {
        return new HandleRequestMiddleware($container);
    }
}
