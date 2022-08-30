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

    public function getAppointments(string $since): \Generator
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
    }

    public function getAppointment(int $id): ?array
    {
        return $this->sendRequest('citas/' . $id)['data'] ?? null;
    }

    public function getPatient(int $id): ?array
    {
        return $this->sendRequest('pacientes/' . $id)['data'] ?? null;
    }

    public function createPatient(array $patient): ?array
    {
        return $this->sendRequest('pacientes', 'POST', $patient)['data'] ?? null;
    }

    public function editPatient(array $patient): ?array
    {
        return $this->sendRequest('pacientes/' . $patient['id'], 'PUT', $patient)['data'] ?? null;
    }

    public function createCita(array $cita): ?array
    {
        return $this->sendRequest('citas', 'POST', $cita)['data'] ?? null;
    }

    public function editCita(array $cita): ?array
    {
        return $this->sendRequest('citas/' . $cita['id'], 'PUT', $cita)['data'] ?? null;
    }

    public function getStatuses(): ?array
    {
        return $this->sendRequest('citas/estados')['data'] ?? null;
    }

    public function getDentistas(): ?array
    {
        return $this->sendRequest('dentistas')['data'] ?? null;
    }

    public function getTratamientos(): ?array
    {
        return $this->sendRequest('tratamientos')['data'] ?? null;
    }

    public function getSucursales(): ?array
    {
        return $this->sendRequest('sucursales')['data'] ?? null;
    }

    public function getSillones(): ?array
    {
        return $this->sendRequest('sillones')['data'] ?? null;
    }

    public function getEspecialidades(): ?array
    {
        return $this->sendRequest('especialidades')['data'] ?? null;
    }

    private function sendRequest(string $url, ?string $method = 'GET', ?array $data = null)
    {
        try {
            $response = $this->httpClient->request($method, $url, array_filter([
                'headers' => [
                    'Authorization' => 'Token ' . $this->token
                ],
                'body' => $data,
            ]));
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
