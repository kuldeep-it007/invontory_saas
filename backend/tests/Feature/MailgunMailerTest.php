<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;

it('sends through the Mailgun API using the sending domain and region from config', function (string $endpoint) {
    config([
        'services.mailgun' => [
            'domain' => 'mg.example.com',
            'secret' => 'key-test',
            'endpoint' => $endpoint,
            'scheme' => 'https',
        ],
    ]);

    $transport = Mail::mailer('mailgun')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(MailgunHttpTransport::class)
        ->and((string) $transport)->toBe("mailgun+https://{$endpoint}?domain=mg.example.com");
})->with(['US region' => 'api.mailgun.net', 'EU region' => 'api.eu.mailgun.net']);

it('defaults to the US Mailgun endpoint when none is configured', function () {
    expect(config('services.mailgun.endpoint'))->toBe('api.mailgun.net');
});
