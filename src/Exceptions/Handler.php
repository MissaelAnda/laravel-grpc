<?php

namespace MissaelAnda\Grpc\Exceptions;

use Closure;
use Google\Rpc\BadRequest\FieldViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Support\Traits\ReflectsClosures;
use Illuminate\Validation\ValidationException;
use MissaelAnda\Grpc\Contracts\ExceptionHandler;
use Spiral\RoadRunner\GRPC\Exception\NotFoundException;
use Spiral\RoadRunner\GRPC\Exception\UnauthenticatedException;
use Spiral\RoadRunner\GRPC\StatusCode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class Handler implements ExceptionHandler
{
    use ReflectsClosures;

    protected string $message;

    /**
     * @var array<Closure(Throwable)>
     */
    protected array $handlers = [];

    /**
     * @var array<string,Closure|class-string>
     */
    protected array $map = [];

    public function __construct(protected Repository $config)
    {
        $this->message = 'Internal Server Error';
    }

    public function handle(Throwable $e)
    {
        $e = $this->mapException($e);

        try {
            foreach ($this->handlers as $type => $handle) {
                if (is_a($e, $type)) {
                    if ($handle($e) === false) {
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
        }

        if (!$e instanceof GrpcException) {
            $e = new GrpcException($this->isDebug() ?
                $e->getMessage() : $this->getMessage(), StatusCode::INTERNAL, [], $e);
        }

        throw $e;
    }

    public function mapException(Throwable $e): Throwable
    {
        foreach ($this->map as $from => $to) {
            if (is_a($e, $from, true)) {
                return is_callable($to) ? $to($e) : new $to($e->getMessage());
            }
        }

        return $e;
    }

    /**
     * @param  Closure(Throwable $e)  $handler
     */
    public function on(Closure $handler): static
    {
        foreach ($this->firstClosureParameterTypes($handler) as $type) {
            $this->handlers[$type] = $handler;
        }

        return $this;
    }

    public function map(Closure|string|array $from, Closure|string|null $to = null): static
    {
        if (is_array($from) && !is_callable($from)) {
            foreach ($from as $item => $to) {
                if (!class_exists($item) && $to !== null) {
                    $item = $to;
                    $to = null;
                }

                $this->map($item, $to);
            }

            return $this;
        }

        if (is_callable($from) && is_null($to)) {
            $from = $this->firstClosureParameterType($to = $from);
        }

        if (!is_string($from) || $to === null) {
            throw new \InvalidArgumentException('Invalid exception mapping.');
        }

        $this->map[$from] = $to;

        return $this;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return __($this->message);
    }

    protected function isDebug(): bool
    {
        return $this->config->get('grpc.server.debug');
    }

    public function boot()
    {
        $this->map([
            RecordsNotFoundException::class => NotFoundException::class,
            NotFoundHttpException::class => NotFoundException::class,
            AuthenticationException::class => UnauthenticatedException::class,
            UnauthorizedHttpException::class => $unauthorized =  fn ($e) => new GrpcException($e->getMessage(), StatusCode::PERMISSION_DENIED),
            AuthorizationException::class => $unauthorized,
            fn (UnprocessableEntityHttpException $e) => new GrpcException($e->getMessage(), StatusCode::INVALID_ARGUMENT),
            fn (ValidationException $e) => new GrpcException($e->getMessage(), StatusCode::INVALID_ARGUMENT, collect($e->errors())
                ->map(fn ($errors, $field) => new FieldViolation(['field' => $field, 'description' => $errors[0]]))->all()),
        ]);
    }
}
