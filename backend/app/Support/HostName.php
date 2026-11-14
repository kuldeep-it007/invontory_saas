<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hostname normalisation and validation for tenant-supplied domains.
 *
 * Everything that stores or looks up a domain goes through {@see normalize()}
 * so the same host always has exactly one spelling (lowercase, punycode, no
 * port, no trailing dot).
 */
final class HostName
{
    /**
     * Normalise a user-supplied value to a bare, lowercase, ASCII hostname, or
     * return null when it is not an acceptable public DNS name.
     *
     * Accepts a pasted URL ("https://Shop.Example.com/path") for convenience.
     * Rejects IP addresses, single-label names (localhost), wildcards, ports,
     * and any label that is not RFC 1123 compliant.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $host) ?? $host;
        $host = preg_replace('#[/?\#].*$#', '', $host) ?? $host;
        $host = rtrim($host, '.');

        if ($host === '' || str_contains($host, '@') || str_contains($host, ':') || str_contains($host, '*')) {
            return null;
        }

        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            if (! function_exists('idn_to_ascii')) {
                return null;
            }
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                return null;
            }
            $host = strtolower($ascii);
        }

        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return null;
        }

        foreach ($labels as $label) {
            if (preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $label) !== 1) {
                return null;
            }
        }

        // The TLD must not be all digits (guards "1.2.3.4"-style and numeric hosts).
        if (preg_match('/^(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', end($labels)) !== 1) {
            return null;
        }

        return $host;
    }

    /**
     * Whether $host equals $base or is a subdomain of it (dot-boundary match,
     * so "evilexample.com" is NOT under "example.com").
     */
    public static function isSameOrSubdomainOf(string $host, string $base): bool
    {
        $host = strtolower(rtrim($host, '.'));
        $base = strtolower(rtrim($base, '.'));

        return $base !== '' && ($host === $base || str_ends_with($host, '.'.$base));
    }
}
