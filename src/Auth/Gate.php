<?php

namespace MissaelAnda\Grpc\Auth;

use Google\Protobuf\Internal\Message;
use Illuminate\Auth\Access\Gate as AccessGate;

class Gate extends AccessGate
{
    protected function guessPolicyName($class)
    {
        $guesses = parent::guessPolicyName($class);

        if (is_a($class, Message::class, true)) {
            array_unshift($guesses, 'App\\Grpc\\Policies' . class_basename($class) . 'Policy');
        }

        return $guesses;
    }
}
