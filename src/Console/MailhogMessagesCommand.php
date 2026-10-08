<?php

namespace Yiendos\MySitesIde\Mail\Mailhog\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Mail\Mailhog\Traits\InteractsWithMailhog;

class MailhogMessagesCommand extends Command
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
            ->setName('mail:mailhog-messages')
            ->setDescription('List the emails MailHog has caught, newest first')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many to show', '10')
        ;
    }

    /**
     * A quick check that a site's mail went out, without opening the UI
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $messages = $this->mailhogApi('GET', "/api/v2/messages?limit={$limit}");

        if ($messages === null) {
            $io->error("Can't reach MailHog at {$this->mailhogUrl()} - start it with mail:mailhog-start.");
            return Command::FAILURE;
        }

        if (($messages['total'] ?? 0) === 0) {
            $io->writeln('No mail caught yet.');
            return Command::SUCCESS;
        }

        $io->table(['Received', 'From', 'To', 'Subject'], array_map(fn (array $message): array => [
            date('Y-m-d H:i:s', strtotime($message['Created'] ?? 'now')),
            $this->header($message, 'From'),
            $this->header($message, 'To'),
            $this->header($message, 'Subject'),
        ], $messages['items'] ?? []));

        $io->writeln("Showing {$messages['count']} of {$messages['total']} - read them at {$this->mailhogUrl()}");

        return Command::SUCCESS;
    }

    /**
     * One header of a message, MIME-decoded (e.g. =?UTF-8?Q?...?= subjects)
     *
     * @param array<mixed> $message
     * @param string $name
     * @return string
     */
    private function header(array $message, string $name): string
    {
        $value = implode(', ', $message['Content']['Headers'][$name] ?? []);

        return function_exists('iconv_mime_decode') ? (iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value) : $value;
    }
}
