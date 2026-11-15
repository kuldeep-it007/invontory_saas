<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Masks secrets in every log record before any handler writes it.
 *
 * Keys that look like credentials are replaced wholesale; free text is scanned
 * for bearer tokens, `password=...`-style pairs and private keys. Applied to the
 * whole log stack, so the plain-text log and the archive are both clean.
 */
class MaskSensitiveData implements ProcessorInterface
{
    public const MASK = '[MASKED]';

    /** Matched against lowercased keys with `-`/`_` removed. */
    private const SENSITIVE_KEY = '/(pass(word|wd|phrase)?|secret|token|apikey|accesskey|privatekey|authorization|cookie|session|otp|pin|cvv|card(number)?|signature|credential)/';

    /** @var list<array{0: string, 1: string}> */
    private const VALUE_PATTERNS = [
        ['/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 '.self::MASK],
        ['/\b(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key)(["\']?\s*[:=]\s*["\']?)[^\s"\'&,;}]+/i', '$1$2'.self::MASK],
        ['/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s', self::MASK],
        ['/\b\d+\|[A-Za-z0-9]{40,}\b/', self::MASK], // Sanctum plain-text token "id|secret"
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->maskString($record->message),
            context: $this->maskArray($record->context),
            extra: $this->maskArray($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function maskArray(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return ['_truncated' => true];
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $data[$key] = $this->maskString($value);
            }
        }

        return $data;
    }

    public function maskString(string $value): string
    {
        foreach (self::VALUE_PATTERNS as [$pattern, $replacement]) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace(['-', '_', ' '], '', strtolower($key));

        // Identifiers and counters that only contain a sensitive word are fine.
        if (in_array($normalized, ['tokenable', 'tokenabletype', 'tokenableid', 'tokenid', 'sessionid', 'pinned'], true)) {
            return false;
        }

        return preg_match(self::SENSITIVE_KEY, $normalized) === 1;
    }
}
