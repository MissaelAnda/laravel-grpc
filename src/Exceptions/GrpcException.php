<?php

namespace MissaelAnda\Grpc\Exceptions;

use MissaelAnda\Grpc\Enums\StatusCode;
use Spiral\RoadRunner\GRPC\Exception\GRPCException as ExceptionGRPCException;

class GrpcException extends ExceptionGRPCException
{
    public static function make(
        string $message = '',
        StatusCode|int $code = StatusCode::UNKNOWN,
        array $details = [],
        \Throwable $previous = null,
    ): self {
        return new self($message, $code instanceof StatusCode ? $code->value : $code, $details, $previous);
    }

    public function getStatusCode(): StatusCode
    {
        return StatusCode::from($this->code);
    }
}
