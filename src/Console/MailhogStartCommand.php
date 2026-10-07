<?php

namespace Yiendos\MySitesIde\Servers\Mailhog\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Mailhog\Traits\InteractsWithMailhog;

class MailhogStartCommand extends Command
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
            ->setName('servers:mailhog-start')
            ->setDescription('Start the MailHog container - or recreate it if its compose config changed')
        ;
    }

    /**
     * Always runs `up -d`, even when mailhog is already running: it's a
     * no-op for an up-to-date container, and recreates one whose compose
     * config has changed - e.g. new MAILHOG_*_PORT values, or a container
     * created before mailhog became a plugin.
     *
     * MailHog keeps mail in memory, so recreating it empties the inbox.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->compose($output, 'up -d mailhog') !== 0) {
            $io->error('MailHog did not start - see above.');
            return Command::FAILURE;
        }

        $io->success('MailHog started - ' . $this->mailhogUrl());
        $io->text([
            "Point a site's .env at it:",
            '',
            '    MAIL_MAILER=smtp',
            '    MAIL_HOST=mailhog',
            '    MAIL_PORT=1025',
            '',
        ]);

        return Command::SUCCESS;
    }
}
