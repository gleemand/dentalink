<?php

namespace App\Service\Dentalink\Factory;

use App\Service\Dentalink\ClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Factory implements FactoryInterface
{
    private string $token;

    private \GuzzleHttp\ClientInterface $httpClient;

    private ClientInterface $client;

    public function __construct(
        \GuzzleHttp\ClientInterface $httpClient,
        ClientInterface $client,
        ContainerBagInterface $params
    ) {
        $this->httpClient = $httpClient;
        $this->client = $client;
        $this->token = $params->get('dentalink.api_token');
    }

    public function create()
    {
        $this->client->setToken($this->token);
        $this->client->setHttpClient($this->httpClient);

        return $this->client;
    }
}