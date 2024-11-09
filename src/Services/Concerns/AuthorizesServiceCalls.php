<?php

namespace MissaelAnda\Grpc\Services\Concerns;

use Google\Protobuf\Internal\Message;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;

trait AuthorizesServiceCalls
{
    use AuthorizesRequests {
        parseAbilityAndArguments as baseParse;
    }

    /**
     * Guesses the ability's name if it wasn't provided.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return array
     */
    protected function parseAbilityAndArguments($ability, $arguments)
    {
        [$ability, $arguments] = $this->baseParse($ability, $arguments);

        // If the function has no arguments or the first argument is a message instance
        // we add the service class name as the first argument so the gate tries to
        // resolve the path App\Grpc\Policies\<ClassService>Policy
        if ((is_array($arguments) && (empty($arguments) || $arguments[0] instanceof Message)) ||
            $arguments instanceof Message
        ) {
            $arguments = Arr::wrap($arguments);
            array_unshift($arguments, static::class);
        }

        // if not we don't add it so the gate tries to resolve models or other classes
        return [$ability, $arguments];
    }
}
