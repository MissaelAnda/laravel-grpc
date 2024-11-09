<?php

declare(strict_types=1);

namespace MissaelAnda\Grpc\Server;

use Closure;
use Illuminate\Support\Traits\Macroable;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * @implements \IteratorAggregate<string, mixed>
 * @implements \ArrayAccess<string, mixed>
 */
class Context implements ContextInterface, \IteratorAggregate, \Countable, \ArrayAccess
{
    use Macroable;

    protected ?\Closure $userResolver = null;

    /**
     * @param TValues $values
     */
    public function __construct(
        protected array $values,
    ) {
    }

    public function withValue(string $key, mixed $value): ContextInterface
    {
        $ctx = clone $this;
        $ctx->values[$key] = $value;

        return $ctx;
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function offsetExists(mixed $offset): bool
    {
        \assert(\is_string($offset), 'Offset argument must be a type of string');

        /**
         * Note: PHP Opcode optimisation
         * @see https://www.php.net/manual/pt_BR/internals2.opcodes.isset-isempty-var.php
         *
         * Priority use `ZEND_ISSET_ISEMPTY_VAR !0` opcode instead of `DO_FCALL 'array_key_exists'`.
         */
        return isset($this->values[$offset]) || \array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): mixed
    {
        \assert(\is_string($offset), 'Offset argument must be a type of string');

        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        \assert(\is_string($offset), 'Offset argument must be a type of string');

        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        \assert(\is_string($offset), 'Offset argument must be a type of string');

        unset($this->values[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->values);
    }

    public function getUserResolver(): callable
    {
        return $this->userResolver ?: function () {
            //
        };
    }

    public function setUserResolver(Closure $callback): static
    {
        $this->userResolver = $callback;

        return $this;
    }

    public function user(?string $guard = null): mixed
    {
        return call_user_func($this->getUserResolver(), $guard);
    }

    public function count(): int
    {
        return \count($this->values);
    }
}
