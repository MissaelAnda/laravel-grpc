<?php

namespace MissaelAnda\Grpc\Server;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Support\DeferrableProvider;
use MissaelAnda\Grpc\Auth\Gate as AuthGate;
use MissaelAnda\Grpc\Server\Invoker;
use MissaelAnda\Grpc\Services\ServicesRegistry;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;

class ServerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->bind(ServicesRegistry::class);
        $this->app->bind(InvokerInterface::class, Invoker::class);

        $this->app->bind(Gate::class, function ($app) {
            return new AuthGate($app, fn () => call_user_func($app['auth']->userResolver()));
        });

        $this->app->alias('context', ContextInterface::class);

        $this->app->rebinding('context', function ($app, $context) {
            $context->setUserResolver(function ($guard = null) use ($app) {
                return call_user_func($app['auth']->userResolver(), $guard);
            });
        });
    }

    public function provides()
    {
        return [ServicesRegistry::class, InvokerInterface::class, 'context'];
    }
}
