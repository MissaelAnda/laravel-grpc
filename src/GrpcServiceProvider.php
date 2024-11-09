<?php

namespace MissaelAnda\Grpc;

use Illuminate\Support\ServiceProvider;
use MissaelAnda\Grpc\Commands;
use MissaelAnda\Grpc\Commands\Server\ServerStateFile;
use MissaelAnda\Grpc\Contracts\ExceptionHandler;
use MissaelAnda\Grpc\Exceptions\Handler;
use MissaelAnda\Grpc\Server\CredentialsFactory;

class GrpcServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/grpc.php', 'grpc');

        $this->app->singleton(CredentialsFactory::class);
        $this->app->singleton(ClientRepository::class);
        $this->app->singleton(ExceptionHandler::class, Handler::class);
        $this->app->bind(ServerStateFile::class, function ($app) {
            return new ServerStateFile($app['config']->get(
                'grpc.state_file',
                storage_path('logs/grpc-server-state.json'),
            ));
        });
    }

    public function boot()
    {
        $this->registerCommands();
        $this->publishResources();

        $this->app->afterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) {
            if (method_exists($handler, 'boot')) {
                $handler->boot();
            }
        });
    }

    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\GenerateProtosCommand::class,
                Commands\GrpcServeCommand::class,
                Commands\StopCommand::class,
                Commands\ReloadCommand::class,
            ]);
        }
    }

    protected function publishResources()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/grpc.php' => config_path('grpc.php'),
            ]);
        }
    }
}
