<?php

namespace MissaelAnda\Grpc\Server;

use Illuminate\Contracts\Config\Repository;

class CredentialsFactory
{
    public function __construct(protected Repository $config)
    {
        // 
    }

    public function createCredentials(): ?\Grpc\ChannelCredentials
    {
        if (!empty($certFilePath = $this->config->get('grpc.client.tls')) && file_exists($certFilePath)) {
            return \Grpc\ChannelCredentials::createSsl(file_get_contents($certFilePath));
        }

        return \Grpc\ChannelCredentials::createInsecure();
    }
}
