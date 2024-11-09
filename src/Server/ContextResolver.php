<?php

namespace MissaelAnda\Grpc\Server;

use Spiral\RoadRunner\GRPC\ContextInterface;

abstract class ContextResolver
{
    public static function instance(): ContextInterface
    {
        return app(ContextInterface::class);
    }
}
