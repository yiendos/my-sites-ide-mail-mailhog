<?php

namespace Yiendos\MySitesIde\Mail\Mailhog\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Mail\Mailhog\Traits\InteractsWithMailhog;

class MailhogClearCommand extends Command
{
    use InteractsWithMailhog;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:mailhog-clear')
            ->setDescription('Delete every email MailHog has caught')
        ;
    }

    /**
     * Handy before a test run, so the inbox only holds what it sends
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->mailhogApi('DELETE', '/api/v1/messages') === null) {
            $io->error("Can't reach MailHog at {$this->mailhogUrl()} - start it with servers:mailhog-start.");
            return Command::FAILURE;
        }

        $io->success('MailHog inbox cleared.');

        return Command::SUCCESS;
    }
}
