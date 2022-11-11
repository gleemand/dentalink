<?php

namespace App\Service\SinceId;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class SinceId implements SinceIdInterface
{
    public const CUSTOMERS = 'customers';
    public const ORDERS = 'orders';

    private Filesystem $filesystem;
    private string $file;
    private LoggerInterface $logger;
    private string $since;
    private ContainerBagInterface $params;

    public function __construct(
        Filesystem $filesystem,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->params = $params;
        $this->logger = $logger;
    }

    public function init(string $entityType): void
    {
        $this->file = __DIR__ . '/../../../' . $this->params->get('app.since_id_file_' . $entityType);
    }

    public function save(): void
    {
        if (!$this->filesystem->exists($this->file)) {
            $this->filesystem->touch($this->file);
        }

        $this->logger->debug('Save SinceId: ' . $this->since);

        $this->filesystem->dumpFile($this->file, $this->since);
    }

    public function get(): ?int
    {
        $since = null;

        if ($this->filesystem->exists($this->file)) {
            $since = file_get_contents($this->file);
        }

        $this->logger->debug('Get SinceId: ' . $since);

        return $since;
    }

    public function set(int $sinceId): void
    {
        $this->since = $sinceId;

        $this->logger->debug('Set SinceId: ' . $this->since);
    }
}
