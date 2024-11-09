<?php

namespace MissaelAnda\Grpc;

use Grpc\Internal\InterceptorChannel;
use Illuminate\Contracts\Config\Repository;
use MissaelAnda\Grpc\Server\CredentialsFactory;

class ClientRepository
{
    /**
     * @var array<class-string<ServiceInterface>,GrpcClient<T>>
     */
    protected array $clients = [];

    public function __construct(
        protected CredentialsFactory $credentialsFactory,
        protected Repository $config,
    ) {
        // 
    }

    /**
     * Get the client instance for the given service interface
     * 
     * @param  class-string<ServiceInterface>  $service
     */
    public function for(
        string $service,
        ?string $hostname = null,
        array $options = [],
        InterceptorChannel|\Grpc\Channel|null $channel = null
    ): GrpcClient {
        $hostname ??= $this->config->get('grpc.client.host') . ':' . $this->config->get('grpc.client.port');

        $options['credentials'] ??= $this->credentialsFactory->createCredentials();

        return $this->clients[$service] ??= new GrpcClient($service, $hostname, $options, $channel);
    }
}
