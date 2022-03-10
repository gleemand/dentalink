<?php

namespace App\Service\Dentalink;

use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    private string $token;

    private \GuzzleHttp\Client $httpClient;

    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function getAppointments($since)
    {
        $query_string = '?q={"fecha_actualizacion":{"gte":"' . $since . '"}}';

        return $this->sendRequest('citas' . $query_string);
    }

    public function getAppointment($id)
    {
        return $this->sendRequest('citas/' . $id);
    }

    public function getPatient($id)
    {
        return $this->sendRequest('pacientes/' . $id);
    }

    private function sendRequest($url)
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->token
                ]
            ]);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());

            return null;
        }

        $result = $response->getBody();

        $this->logger->debug('Dentalink response: ' . print_r($result, true));

        return $result;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setHttpClient(\GuzzleHttp\Client $httpClient): void
    {
        $this->httpClient = $httpClient;
    }
}