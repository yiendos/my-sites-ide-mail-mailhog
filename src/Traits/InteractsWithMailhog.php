<?php

namespace Yiendos\MySitesIde\Mail\Mailhog\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the mailhog compose service from the host, and talks to MailHog's
 * HTTP API on its published UI port. Every `docker compose` call runs from
 * the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithMailhog
{
    /**
     * Whether the mailhog container is up
     *
     * @return bool
     */
    protected function mailhogRunning(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running mailhog 2>/dev/null')) !== '';
    }

    /**
     * The web UI and API, as reached from the host
     *
     * @return string
     */
    protected function mailhogUrl(): string
    {
        return 'http://localhost:' . (getenv('MAILHOG_UI_PORT') ?: '8025');
    }

    /**
     * Calls MailHog's HTTP API, e.g. GET /api/v2/messages
     *
     * @param string $method
     * @param string $path
     * @return array<mixed>|null the decoded JSON body (an empty array for an empty body), null when the call failed
     */
    protected function mailhogApi(string $method, string $path): ?array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'timeout' => 5, 'ignore_errors' => false]]);
        $body = @file_get_contents($this->mailhogUrl() . $path, false, $context);

        if ($body === false) {
            return null;
        }

        return trim($body) === '' ? [] : (json_decode($body, true) ?? []);
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
