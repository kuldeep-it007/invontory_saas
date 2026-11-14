<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Refuses tenant-supplied hosts that point at the server's own network.
 *
 * SMTP hosts and storage endpoints are chosen by tenants, and the server opens
 * the connection. Without this check a tenant could aim it at loopback, the
 * private network, or the cloud metadata address and read the error text.
 *
 * Every address the name resolves to must be public. The check runs when a
 * configuration is tested and saved. A name that is later re-pointed at a
 * private address (DNS rebinding) is not re-checked on each send; an egress
 * proxy or firewall rule is the complete answer to that.
 */
class OutboundHostGuard
{
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  Returns the IPs a host resolves to; overridable in tests.
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                $ips[] = (string) ($record['ipv6'] ?? '');
            }

            return array_values(array_filter($ips));
        };
    }

    /**
     * @throws ValidationException When the host cannot be resolved or resolves to a non-public address.
     */
    public function assertPublic(string $host, string $field): void
    {
        if (config('integrations.allow_private_hosts')) {
            return;
        }

        $host = strtolower(trim($host, '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);

        if ($ips === []) {
            throw ValidationException::withMessages([$field => 'This host could not be resolved.']);
        }

        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw ValidationException::withMessages([$field => 'This host is not allowed.']);
            }
        }
    }

    public function isPublic(string $ip): bool
    {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mapped) === 1) {
            $ip = $mapped[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Ranges PHP's flags do not cover on every version: CGNAT, link-local, "this network".
        $long = ip2long($ip);
        if ($long !== false) {
            foreach ([['100.64.0.0', 10], ['169.254.0.0', 16], ['0.0.0.0', 8]] as [$base, $bits]) {
                $mask = -1 << (32 - $bits);
                if (($long & $mask) === (ip2long($base) & $mask)) {
                    return false;
                }
            }
        }

        return true;
    }
}
