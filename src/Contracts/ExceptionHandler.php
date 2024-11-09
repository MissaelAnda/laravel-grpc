<?php

namespace MissaelAnda\Grpc\Contracts;

use Throwable;

interface ExceptionHandler
{
    /**
     * Handle the exception thrown during the rpc execution
     */
    public function handle(Throwable $e);
}
