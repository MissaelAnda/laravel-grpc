<?php

namespace MissaelAnda\Grpc\Server;

use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Facade;
use MissaelAnda\Grpc\Contracts\ExceptionHandler;
use MissaelAnda\Grpc\Services\BaseService;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;

class Invoker implements InvokerInterface
{
    protected const ERROR_METHOD_RETURN =
    'Method %s must return an object that instance of %s, but the result provides type of %s';

    protected const ERROR_METHOD_IN_TYPE =
    'Method %s input type must be an instance of %s, but the input is type of %s';

    protected array $interceptors = [];

    public function __construct(
        protected Application $app,
        protected ExceptionHandler $handler,
    ) {
        $this->loadGlobalInterceptors();
    }

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        /** @var callable $callable */
        $callable = [$service, $method->name];

        $input = $input instanceof Message ? $input : $this->makeInput($method, $input);

        // We will clone the application instance so that we have a clean copy to switch
        // back to once the request has been handled. This allows us to easily delete
        // certain instances that got resolved / mutated during a previous request.
        $this->setCurrentApp($app = clone $this->app);
        $this->registerConsoleRequest($app);
        $ctx = $this->registerServiceCallContext($app, $ctx);

        try {
            $message = (new Pipeline($app))
                ->send(new GrpcRequest($ctx, $input))
                ->through($this->buildInterceptors($service, $method))
                ->then(fn (GrpcRequest $request) => $callable($request->ctx, $request->message));

            \assert($this->assertResultType($method, $message));

            return $message->serializeToString();
        } catch (\Throwable $e) {
            $this->handler->handle($e);
        } finally {
            $this->terminate($app);
        }
    }

    /**
     * Registers the current service call context instance
     */
    protected function registerServiceCallContext(Application $app, ContextInterface $ctx): ContextInterface
    {
        // TODO: allow users to modify this step with IoC
        $ctx = new Context($ctx->getValues());
        $app->instance('context', $ctx);
        Facade::clearResolvedInstance('context');

        return $ctx;
    }

    /**
     * Creates a clean console request for each service call
     * Required to prevent cache headers from previous requests
     */
    protected function registerConsoleRequest(Application $app)
    {
        $uri = $app->make('config')->get('app.url', 'http://localhost');

        $components = parse_url($uri);

        $server = $_SERVER;

        if (isset($components['path'])) {
            $server = array_merge($server, [
                'SCRIPT_FILENAME' => $components['path'],
                'SCRIPT_NAME' => $components['path'],
            ]);
        }

        $app->instance('request', Request::create(
            $uri,
            'GET',
            [],
            [],
            [],
            $server
        ));
        Facade::clearResolvedInstance('request');
    }

    protected function terminate(Application $disposeApp): void
    {
        $disposeApp->terminate();
        $disposeApp->flush();

        unset($disposeApp);

        $this->setCurrentApp($this->app);
    }

    protected function setCurrentApp(Application $app): void
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        \Illuminate\Container\Container::setInstance($app);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }

    /**
     * Builds the interceptors pipeline
     */
    protected function buildInterceptors(ServiceInterface $service, Method $method): array
    {
        if ($service instanceof BaseService) {
            return array_merge($this->interceptors, $service->getInterceptorsForService($method->name));
        }

        return $this->interceptors;
    }

    /**
     * Checks that the result from the GRPC service method returns the Message object.
     *
     * @throws \BadFunctionCallException
     */
    protected function assertResultType(Method $method, mixed $result): bool
    {
        if (!$result instanceof Message) {
            $type = \get_debug_type($result);

            throw new \BadFunctionCallException(
                \sprintf(self::ERROR_METHOD_RETURN, $method->name, Message::class, $type),
            );
        }

        return true;
    }

    /**
     * Converts the input from the GRPC service method to the Message object.
     * @throws InvokeException
     */
    protected function makeInput(Method $method, ?string $body): Message
    {
        try {
            $class = $method->inputType;
            \assert($this->assertInputType($method, $class));

            $in = new $class();

            if ($body !== null) {
                $in->mergeFromString($body);
            }

            return $in;
        } catch (\Throwable $e) {
            throw InvokeException::create($e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }

    /**
     * Checks that the input of the GRPC service method contains the
     * Message object.
     *
     * @param class-string $class
     * @throws \InvalidArgumentException
     */
    protected function assertInputType(Method $method, string $class): bool
    {
        if (!\is_subclass_of($class, Message::class)) {
            throw new \InvalidArgumentException(
                \sprintf(self::ERROR_METHOD_IN_TYPE, $method->name, Message::class, $class),
            );
        }

        return true;
    }

    protected function loadGlobalInterceptors()
    {
        /** @var Repository */
        $config = $this->app->make('config');

        $this->interceptors = $config->get('grpc.interceptors', []);
    }
}
