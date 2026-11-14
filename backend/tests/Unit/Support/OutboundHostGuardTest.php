<?php

declare(strict_types=1);

use App\Support\OutboundHostGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a name that resolves only to public addresses', function () {
    $guard = new OutboundHostGuard(fn () => ['93.184.216.34']);

    $guard->assertPublic('smtp.example.com', 'host');

    expect(true)->toBeTrue();
});

it('rejects private, loopback, link-local and reserved addresses', function (string $ip) {
    expect((new OutboundHostGuard)->isPublic($ip))->toBeFalse();
})->with([
    'loopback' => '127.0.0.1',
    'private 10/8' => '10.1.2.3',
    'private 172.16/12' => '172.20.0.5',
    'private 192.168/16' => '192.168.1.10',
    'cloud metadata' => '169.254.169.254',
    'carrier-grade NAT' => '100.64.0.1',
    'this network' => '0.0.0.0',
    'ipv6 loopback' => '::1',
    'ipv6 unique local' => 'fd00::1',
    'ipv4-mapped private' => '::ffff:10.0.0.5',
]);

it('rejects a hostname when any one of its addresses is private', function () {
    $guard = new OutboundHostGuard(fn () => ['93.184.216.34', '10.0.0.5']);

    expect(fn () => $guard->assertPublic('mixed.example.com', 'host'))
        ->toThrow(ValidationException::class, 'not allowed');
});

it('rejects a name that does not resolve', function () {
    $guard = new OutboundHostGuard(fn () => []);

    expect(fn () => $guard->assertPublic('nowhere.example.com', 'host'))
        ->toThrow(ValidationException::class, 'could not be resolved');
});

it('rejects an IP literal without resolving it', function () {
    $guard = new OutboundHostGuard(fn () => throw new RuntimeException('must not resolve'));

    expect(fn () => $guard->assertPublic('127.0.0.1', 'host'))->toThrow(ValidationException::class);
});

it('allows private hosts only when explicitly enabled for local development', function () {
    config(['integrations.allow_private_hosts' => true]);

    (new OutboundHostGuard(fn () => ['10.0.0.5']))->assertPublic('mailhog', 'host');

    expect(true)->toBeTrue();
});
