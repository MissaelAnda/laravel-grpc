<?php

namespace MissaelAnda\Grpc;

use BadMethodCallException;
use Grpc\Internal\InterceptorChannel;
use InvalidArgumentException;
use MissaelAnda\Grpc\Enums\StatusCode;
use MissaelAnda\Grpc\Exceptions\GrpcException;
use ReflectionClass;
use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * @template T
 * 
 * @mixin T
 */
class GrpcClient extends \Grpc\BaseStub
{
    /**
     * @var array<string,array<{"method":string,"return":string}>>
     */
    protected array $methods;

    /**
     * The service interface NAME constant value
     */
    protected string $serviceName;

    /**
     * @param  class-string<ServiceInterface>  $service
     */
    public function __construct(
        string $service,
        string $hostname,
        array $opts = [],
        InterceptorChannel|\Grpc\Channel|null $channel = null
    ) {
        if (!interface_exists($service) || !is_a($service, ServiceInterface::class, true)) {
            throw new InvalidArgumentException("$service is not a valid gRPC service interface.");
        }

        $reflection = new ReflectionClass($service);
        $this->serviceName = $reflection->getConstant('NAME');

        $this->extractMethods($reflection);

        parent::__construct($hostname, $opts, $channel);
    }

    protected function extractMethods(ReflectionClass $reflection)
    {
        foreach ($reflection->getMethods() as $method) {
            $this->methods[$method->getName()] = [
                'message' => $method->getParameters()[1]->getType()->getName(),
                'return' => $method->getReturnType()->getName(),
            ];
        }
    }

    public function __call($name, $arguments)
    {
        if (!array_key_exists($name, $this->methods)) {
            throw new BadMethodCallException("The method $name does not exist for service {$this->serviceName}.");
        }

        $method = $this->methods[$name];

        if (count($arguments) === 0 || !$arguments[0] instanceof $method['message']) {
            throw new InvalidArgumentException("$name expects the first parameter to be an instance of $method[message].");
        }

        [$response, $status] = $this->_simpleRequest(
            '/' . $this->serviceName . '/' . $name,
            $arguments[0],
            [$method['return'], 'decode'],
            is_array($arguments[1] ?? false) ? $arguments[1] : [],
            is_array($arguments[2] ?? false) ? $arguments[2] : [],
        )->wait();

        $code = $status->code ?? StatusCode::UNKNOWN->value;

        if ($code !== StatusCode::OK->value) {
            throw new GrpcException($status->details, $status->code, $status->metadata['grpc-status-details-bin'] ?? []);
        }

        return $response;
    }
}
