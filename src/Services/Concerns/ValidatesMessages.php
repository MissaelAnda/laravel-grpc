<?php

namespace MissaelAnda\Grpc\Services\Concerns;

use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Validation\Validator;
use MissaelAnda\Grpc\Utils;

trait ValidatesMessages
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(Message $message, array $rules, array $messages = []): array
    {
        $validator = $this->makeValidator($message, $rules, $messages);

        return $validator->validate();
    }

    protected function makeValidator(Message $message, array $rules, array $messages = []): Validator
    {
        return $this->getValidationFactory()->make(Utils::extractMessageFields($message), $rules, $messages);
    }

    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function getValidationFactory()
    {
        return app(\Illuminate\Contracts\Validation\Factory::class);
    }
}
