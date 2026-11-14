<?php

declare(strict_types=1);

use App\Support\HostName;

it('normalizes a host to lowercase ASCII without scheme, path or trailing dot', function (string $input, string $expected) {
    expect(HostName::normalize($input))->toBe($expected);
})->with([
    'plain' => ['shop.example.com', 'shop.example.com'],
    'uppercase' => ['Shop.Example.COM', 'shop.example.com'],
    'pasted url' => ['https://Shop.Example.com/path?x=1', 'shop.example.com'],
    'trailing dot' => ['example.com.', 'example.com'],
    'surrounding spaces' => ['  example.store  ', 'example.store'],
    'internationalized' => ['bücher.example', 'xn--bcher-kva.example'],
]);

it('rejects values that are not a public DNS name', function (?string $input) {
    expect(HostName::normalize($input))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'single label' => ['localhost'],
    'ipv4' => ['203.0.113.10'],
    'ipv6' => ['[::1]'],
    'port' => ['example.com:8080'],
    'wildcard' => ['*.example.com'],
    'credentials' => ['user@example.com'],
    'leading hyphen' => ['-bad.example.com'],
    'underscore' => ['bad_label.example.com'],
    'empty label' => ['a..example.com'],
    'numeric tld' => ['example.123'],
    'label over 63 chars' => [str_repeat('a', 64).'.example.com'],
    'name over 253 chars' => [str_repeat('a.', 130).'com'],
]);

it('matches subdomains on a dot boundary only', function () {
    expect(HostName::isSameOrSubdomainOf('acme.example.com', 'example.com'))->toBeTrue()
        ->and(HostName::isSameOrSubdomainOf('example.com', 'example.com'))->toBeTrue()
        ->and(HostName::isSameOrSubdomainOf('evilexample.com', 'example.com'))->toBeFalse()
        ->and(HostName::isSameOrSubdomainOf('example.com.evil.io', 'example.com'))->toBeFalse();
});
