<?php

namespace MissaelAnda\Grpc\Interceptors;

use Closure;
use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Request;
use MissaelAnda\Grpc\Contracts\Interceptor;
use MissaelAnda\Grpc\Server\GrpcRequest;

/**
 * Inject metadata context to the request headers
 * this will make token authentication work
 */
class InjectMetadataHeaders implements Interceptor
{
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * @param  \Closure(GrpcRequest $request): Message  $next
     */
    public function handle(GrpcRequest $request, Closure $next, string ...$args): Message
    {
        $httpRequest = Request::instance();
        foreach ($request->ctx->getValues() as $key => $value) {
            // We force the value to be transformed into an array so the Request HeaderBag doesn't crash
            $value = collect($value)->toArray();
            $httpRequest->headers->set($key, $value);
        }

        $this->container->instance('request', $httpRequest);
        Facade::clearResolvedInstance('request');

        return $next($request);
    }
}
