<?php

declare(strict_types=1);

namespace App\Services\TenantIntegrations;

use App\Exceptions\StorageUnavailableException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Proves a tenant's SMTP or storage settings actually work, before they are saved.
 *
 * Failures are rethrown as a RuntimeException whose message is safe to show the
 * admin: secrets are stripped, and only the first line of the cause is kept.
 */
class ConnectionTester
{
    /**
     * Connect and authenticate against the SMTP server; optionally send a message.
     *
     * @param  array<string, mixed>  $smtp
     *
     * @throws RuntimeException
     */
    public function smtp(array $smtp, ?string $sendTo = null): void
    {
        try {
            $mailer = Mail::build(IntegrationConfig::mailer($smtp));
            $transport = $mailer->getSymfonyTransport();
            $transport->start();
            $transport->stop();

            if ($sendTo !== null) {
                $mailer->raw(
                    'This is a test message confirming your SMTP settings work.',
                    fn ($message) => $message
                        ->from((string) $smtp['from_address'], ($smtp['from_name'] ?? '') !== '' ? $smtp['from_name'] : null)
                        ->to($sendTo)
                        ->subject('SMTP connection test'),
                );
            }
        } catch (Throwable $e) {
            throw new RuntimeException($this->redact($this->innermostMessage($e), $smtp));
        }
    }

    /**
     * Write, read back and delete a small object in the tenant's bucket.
     *
     * @param  array<string, mixed>  $storage
     *
     * @throws RuntimeException
     */
    public function storage(array $storage, string $tenantId): void
    {
        $path = sprintf('tenants/%s/.healthcheck/%s.txt', $tenantId, Str::random(16));
        $probe = Str::random(24);

        try {
            $disk = Storage::build(IntegrationConfig::disk($storage));
            $disk->put($path, $probe);
            $read = $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $e) {
            throw new RuntimeException($this->redact(StorageUnavailableException::describe($e), $storage));
        }

        if ($read !== $probe) {
            throw new RuntimeException('The bucket returned different data than was written.');
        }
    }

    private function innermostMessage(Throwable $e): string
    {
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        return strtok($e->getMessage(), "\n") ?: 'The connection failed.';
    }

    /**
     * Strip any credential from a message and cap its length.
     *
     * @param  array<string, mixed>  $config
     */
    private function redact(string $message, array $config): string
    {
        foreach (['password', 'secret_key', 'access_key'] as $secret) {
            if (($config[$secret] ?? '') !== '') {
                $message = str_replace((string) $config[$secret], '***', $message);
            }
        }

        return mb_strimwidth($message, 0, 300, '…');
    }
}
