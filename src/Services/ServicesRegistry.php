<?php

namespace MissaelAnda\Grpc\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\GRPC\ServiceInterface;

class ServicesRegistry
{
    /**
     * @var array<class-string>
     */
    protected array $services = [];

    public function __construct(protected Application $app)
    {
        // 
    }

    /**
     * Loads the services from the configuration paths
     */
    public function loadServices(): static
    {
        /** @var Repository */
        $config = $this->app->make('config');

        if (!is_array($paths = $config->get('grpc.paths.services'))) {
            throw new \RuntimeException("The 'paths.services' array config is required.");
        }

        foreach ($paths as $namespace => $path) {
            $path = base_path($path);
            $files = array_merge(glob("$path/*.php"), glob("$path/**/*.php"));

            foreach ($files as $file) {
                $class = str_replace([$path, '/', '.php'], [$namespace, '\\', ''], $file);

                if (!class_exists($class) || !is_a($class, ServiceInterface::class, true)) {
                    continue;
                }

                $interface = Arr::first(
                    class_implements($class),
                    fn ($interface) => is_a($interface, ServiceInterface::class, true) &&
                        $interface !== ServiceInterface::class,
                );

                if (!empty($interface)) {
                    $this->services[$interface] = $class;
                }
            }
        }

        return $this;
    }

    public function registerServices(Server &$server): static
    {
        foreach ($this->services as $serviceInterface => $serviceClass) {
            $server->registerService($serviceInterface, $this->app->make($serviceClass));
        }

        return $this;
    }
}
