<?php

namespace MissaelAnda\Grpc\Services;

use Illuminate\Support\Str;

class ServiceInterceptorsOptions
{
    public function __construct(protected array &$options)
    {
        $this->only($this->options['only'] ?? []);
        $this->except($this->options['except'] ?? []);
    }

    /**
     * Set the service methods the interceptors should apply to.
     *
     * @param  array|string|mixed  $methods
     * @return $this
     */
    public function only($methods)
    {
        $this->options['only'] = $this->parse(is_array($methods) ? $methods : func_get_args());

        return $this;
    }

    /**
     * Set the service methods the interceptors should exclude.
     *
     * @param  array|string|mixed  $methods
     * @return $this
     */
    public function except($methods)
    {
        $this->options['except'] = $this->parse(is_array($methods) ? $methods : func_get_args());

        return $this;
    }

    /**
     * @param  array<string>  $methods
     * @return array<string>
     */
    protected function parse(array $methods): array
    {
        return array_values(array_map(fn ($method) => Str::camel($method), $methods));
    }
}
