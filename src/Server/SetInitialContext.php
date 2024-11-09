<?php

namespace MissaelAnda\Grpc\Server;

use Illuminate\Contracts\Foundation\Application;

class SetInitialContext
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $app->instance('context', new Context([]));
    }
}
