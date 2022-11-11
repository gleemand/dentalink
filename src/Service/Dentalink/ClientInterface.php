<?php

namespace App\Service\Dentalink;

interface ClientInterface
{
    public function getAppointments(string $since): \Generator;
    public function getAppointment(int $id): ?array;

    public function getPatient(int $id): ?array;
    public function createPatient(array $patient): ?array;
    public function editPatient(array $patient): ?array;

    public function setToken(string $token): void;
    public function setHttpClient(\GuzzleHttp\Client $httpClient): void;
}