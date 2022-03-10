<?php

namespace App\Service\SinceDateTime;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class SinceDateTime implements SinceDateTimeInterface
{
    private Filesystem $filesystem;

    private string $file;

    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->file = $params->get('app.since_file');
        $this->logger = $logger;
    }

    public function get()
    {
        $since = (new \DateTime())
            ->sub(new \DateInterval('P1W'))
            ->format('Y-m-d H:i:s');

        if ($this->filesystem->exists($this->file)) {
            $since = file_get_contents($this->file);
        }

        $this->logger->debug('Get SinceDateTime: ' . $since);

        return $since;
    }

    public function set()
    {
        if (!$this->filesystem->exists($this->file)) {
            $this->filesystem->touch($this->file);
        }

        $since = (new \DateTime())
            ->format('Y-m-d H:i:s');

        $this->logger->debug('Set SinceDateTime: ' . $since);

        $this->filesystem->dumpFile($this->file, $since);
    }
}