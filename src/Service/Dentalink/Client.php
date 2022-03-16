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
        $url = 'citas' . '?q={"fecha_actualizacion":{"gte":"' . $since . '"}}';

        do {
            $result = $this->sendRequest($url);

            if (isset($result['data']) && count($result['data'])) {
                foreach ($result['data'] as $appointment) {
                    yield $appointment;
                }
            }

            if (isset($result['links']['next'])) {
                $url = $result['links']['next'];
            }
        } while (isset($result['links']['next']));

        return $this->sendRequest($url);
    }

    public function getAppointment($id)
    {
        return $this->sendRequest('citas/' . $id)['data'] ?? null;
    }

    public function getPatient($id)
    {
        return $this->sendRequest('pacientes/' . $id)['data'] ?? null;
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

        return json_decode($response->getBody()->getContents(), true);
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