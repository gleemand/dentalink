<?php

namespace App\Command;

use App\Service\Sync\PaymentSyncInterface;
use App\Service\Sync\SyncInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'sync';

    protected static $defaultDescription = 'Start sync';

    private SyncInterface $syncService;

    private PaymentSyncInterface $paymentSync;

    public function __construct(
        SyncInterface $syncService,
        PaymentSyncInterface $paymentSync
    ) {
        $this->syncService = $syncService;
        $this->paymentSync = $paymentSync;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('Command is already running');

            return Command::SUCCESS;
        }

        $this->syncService->run();
        $this->paymentSync->run();

        $this->release();

        return Command::SUCCESS;
    }
}
