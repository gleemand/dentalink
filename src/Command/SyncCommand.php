<?php

namespace App\Command;

use App\Service\Sync\SyncInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'sync';

    protected static $defaultDescription = 'Start sync';

    private SyncInterface $syncService;

    public function __construct(
        SyncInterface $syncService
    ) {
        $this->syncService = $syncService;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('Command is already running');

            return Command::SUCCESS;
        }

        $this->syncService->run();

        $this->release();

        return Command::SUCCESS;
    }
}
