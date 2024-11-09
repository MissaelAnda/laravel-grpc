<?php

namespace MissaelAnda\Grpc\Facades;

use Illuminate\Support\Facades\Facade;
use MissaelAnda\Grpc\ClientRepository;
use MissaelAnda\Grpc\GrpcClient;

/**
 * @method static GrpcClient for(string $service)
 */
class Client extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ClientRepository::class;
    }
}
