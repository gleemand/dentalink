<?php

namespace App\Command;

use App\Service\Sync\SyncInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncCommand extends Command
{
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
        $io = new SymfonyStyle($input, $output);

        $this->syncService->run();

        $io->success('Done');

        return Command::SUCCESS;
    }
}
