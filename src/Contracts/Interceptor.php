<?php

namespace MissaelAnda\Grpc\Contracts;

use Google\Protobuf\Internal\Message;
use MissaelAnda\Grpc\Server\GrpcRequest;

interface Interceptor
{
    /**
     * @param  \Closure(GrpcRequest $request): Message  $next
     */
    public function handle(GrpcRequest $request, \Closure $next, string ...$args): Message;
}
