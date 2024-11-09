<?php

namespace MissaelAnda\Grpc\Interceptors;

use Google\Protobuf\Internal\Message;
use MissaelAnda\Grpc\Contracts\Interceptor;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository;
use MissaelAnda\Grpc\Server\GrpcRequest;
use Spiral\RoadRunner\GRPC\Exception\UnauthenticatedException;

class Authenticate implements Interceptor
{
    public function __construct(protected Auth $auth, protected Repository $config)
    {
        //
    }

    /**
     * @param  \Closure(GrpcRequest $request): Message  $next
     */
    public function handle(GrpcRequest $request, \Closure $next, string ...$args): Message
    {
        $this->authenticate($args);

        return $next($request);
    }

    protected function authenticate(array $guards)
    {
        if (empty($guards)) {
            $guards = $this->config->get('grpc.guard') ?: [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        throw new UnauthenticatedException('Unauthenticated.');
    }
}
