<?php

namespace App\Service\Dentalink\Factory;

use App\Service\Dentalink\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Factory implements FactoryInterface
{
    private string $token;

    private string $url;

    private Client $client;

    public function __construct(
        ContainerBagInterface $params,
        Client $client
    ) {
        $this->token = $params->get('dentalink.api_token');
        $this->url = $params->get('dentalink.api_url');
        $this->client = $client;
    }

    public function create()
    {
        $this->client->setToken($this->token);
        $this->client->setHttpClient(new \GuzzleHttp\Client(['base_uri' => $this->url]));

        return $this->client;
    }
}