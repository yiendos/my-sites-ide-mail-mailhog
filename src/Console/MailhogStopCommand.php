<?php

namespace Yiendos\MySitesIde\Mail\Mailhog\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Mail\Mailhog\Traits\InteractsWithMailhog;

class MailhogStopCommand extends Command
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
            ->setName('mail:mailhog-stop')
            ->setDescription('Stop the MailHog container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so mail:mailhog-start brings back the
     * same container. The next ide:spark starts it again (autostart). Mail
     * is kept in memory, so stopping empties the inbox.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->mailhogRunning()) {
            $io->writeln('MailHog is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop mailhog') !== 0) {
            $io->error('MailHog did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('MailHog stopped.');

        return Command::SUCCESS;
    }
}
