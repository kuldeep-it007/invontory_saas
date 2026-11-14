<?php

declare(strict_types=1);

namespace App\Services\Dns;

/**
 * Thin wrapper over PHP's DNS functions so domain verification can be tested
 * with a fake and the lookups share one failure policy: any error or timeout
 * is "no records", never an exception.
 */
class DnsResolver
{
    /**
     * TXT record strings published at $name.
     *
     * @return list<string>
     */
    public function txt(string $name): array
    {
        return array_values(array_map(
            static fn (array $record): string => (string) ($record['txt'] ?? ''),
            $this->records($name, DNS_TXT),
        ));
    }

    /**
     * IPv4 addresses $host resolves to (CNAME chains are followed by the resolver).
     *
     * @return list<string>
     */
    public function a(string $host): array
    {
        return array_values(array_filter(array_map(
            static fn (array $record): string => (string) ($record['ip'] ?? ''),
            $this->records($host, DNS_A),
        )));
    }

    /**
     * CNAME targets directly published at $host (lowercase, no trailing dot).
     *
     * @return list<string>
     */
    public function cname(string $host): array
    {
        return array_values(array_filter(array_map(
            static fn (array $record): string => strtolower(rtrim((string) ($record['target'] ?? ''), '.')),
            $this->records($host, DNS_CNAME),
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $name, int $type): array
    {
        $records = @dns_get_record($name, $type);

        return is_array($records) ? array_values($records) : [];
    }
}
