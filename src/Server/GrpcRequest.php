<?php

namespace MissaelAnda\Grpc\Server;

use Google\Protobuf\Internal\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Simple wrapper for context and message to send through the pipe of interceptors
 */
class GrpcRequest
{
    public function __construct(
        public ContextInterface $ctx,
        public Message $message,
    ) {
        // 
    }
}
