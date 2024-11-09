<?php

namespace MissaelAnda\Grpc\Facades;

use Illuminate\Support\Facades\Facade;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * @mixin ContextInterface
 */
class Context extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'context';
    }

    public static function instance(): ContextInterface
    {
        return app('context');
    }
}
