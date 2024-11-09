<?php

namespace MissaelAnda\Grpc\Services;

use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;
use MissaelAnda\Grpc\Utils;
use MissaelAnda\Grpc\Contracts\Interceptor;

abstract class BaseService
{
    /**
     * @var array<{"interceptor":Interceptor,"except":string[],"only":string[]}>
     */
    protected array $interceptors = [];

    /**
     * @param  array<class-string|class-string>  $interceptors
     * @param  array<array<{"only":string[],"except":string[]}>  $options
     */
    protected function interceptors(array|string $interceptors, array $options = []): ServiceInterceptorsOptions
    {
        foreach ((array)$interceptors as $interceptor) {
            $this->interceptors[] = [
                'interceptor' => $interceptor,
                'options'     => &$options,
            ];
        }

        return new ServiceInterceptorsOptions($options);
    }

    /**
     * @return array<Interceptor>
     */
    public function getInterceptors(): array
    {
        return $this->getInterceptorFor($this->interceptors);
    }

    /**
     * Extract interceptors for specific service
     */
    public function getInterceptorsForService(string $function): array
    {
        $function = Str::camel($function);

        return $this->getInterceptorFor(array_filter(
            $this->interceptors,
            fn ($interceptor) => (empty($interceptor['options']['except']) && empty($interceptor['options']['only']))
                || (count($interceptor['options']['except']) && !in_array($function, $interceptor['options']['except']))
                || (count($interceptor['options']['only']) && in_array($function, $interceptor['options']['only'])),
        ));
    }

    protected function getInterceptorFor(array $interceptors): array
    {
        return array_map(fn ($interceptor) => $interceptor['interceptor'], $interceptors);
    }
}
