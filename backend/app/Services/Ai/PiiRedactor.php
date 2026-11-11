<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Redacts personally identifiable information (PII) from a report dataset
 * BEFORE any of it is transmitted to the language model (R32.3).
 *
 * The guardrail the design states is twofold — "minimum aggregated data sent,
 * PII redacted before transmission" (design "AI Reporting Layer"). This class
 * owns the second half: given the aggregated rows the {@see DatasetMinimizer}
 * has already reduced, it strips PII so no personal value ever leaves the
 * platform in a model prompt.
 *
 * Redaction is applied in two complementary ways so a leak cannot slip through
 * either a known column name or an unexpected free-text value:
 *
 *   1. **Key-based** — any column whose name matches a known PII key (name,
 *      email, phone, gstin, address, contact, etc., matched case-insensitively
 *      as a whole word or sub-token) has its VALUE replaced with the redaction
 *      placeholder regardless of content. This covers the common case where a
 *      report row carries a labelled personal field.
 *
 *   2. **Pattern-based** — any remaining STRING value is scanned for PII-shaped
 *      substrings (email addresses, long digit runs such as phone numbers, and
 *      Indian GSTINs) and those substrings are masked in place. This covers a
 *      personal value that appears inside an otherwise non-PII column.
 *
 * Numbers, booleans, nulls, and aggregate measures (counts, sums, quantities)
 * are preserved untouched — those are exactly the "aggregated/derived data" the
 * model is allowed to see (R32.3). The redactor is pure and stateless: it maps
 * input rows to redacted rows without side effects or I/O, so it is trivially
 * testable and order-independent.
 */
class PiiRedactor
{
    /**
     * The placeholder a redacted value (or masked substring) is replaced with.
     * A fixed, non-reversible token so the model receives structure without the
     * underlying personal value.
     */
    public const PLACEHOLDER = '[REDACTED]';

    /**
     * Column-name tokens that mark a field as PII. Matched case-insensitively
     * against each column name split on non-alphanumeric boundaries, so
     * `buyer_name`, `customerName`, `contact_email`, and `gstin` all match.
     *
     * @var list<string>
     */
    private const PII_KEY_TOKENS = [
        'name',
        'email',
        'phone',
        'mobile',
        'contact',
        'address',
        'street',
        'city',
        'pincode',
        'zip',
        'gstin',
        'gst',
        'pan',
        'ssn',
        'dob',
        'birthdate',
        'firstname',
        'lastname',
        'fullname',
        'username',
        'person',
    ];

    /**
     * Column-name tokens that look PII-ish but are safe grouping/aggregate
     * dimensions, so they are NOT redacted. For example a report grouped by
     * `product_name` or `category_name` describes catalog structure, not a
     * person; redacting it would destroy the very grouping the report conveys.
     *
     * @var list<string>
     */
    private const SAFE_KEY_PREFIXES = [
        'product',
        'category',
        'collection',
        'colour',
        'color',
        'company',
        'stock_point',
        'stockpoint',
        'brand',
    ];

    /**
     * Email address pattern (local@domain.tld).
     */
    private const EMAIL_PATTERN = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    /**
     * A run of 7+ digits (optionally separated by spaces/dashes) — phone
     * numbers, long account/contact numbers.
     */
    private const PHONE_PATTERN = '/(?<!\d)(?:\+?\d[\d\s\-]{6,}\d)(?!\d)/';

    /**
     * Indian GSTIN: 2 digit state code, 10 char PAN, 1 entity digit, Z,
     * 1 checksum char.
     */
    private const GSTIN_PATTERN = '/\b\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z\d]Z[A-Z\d]\b/i';

    /**
     * Redact PII from a list of dataset rows (R32.3).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function redactRows(array $rows): array
    {
        return array_values(array_map(
            fn (array $row): array => $this->redactRow($row),
            $rows,
        ));
    }

    /**
     * Redact PII from a single row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redactRow(array $row): array
    {
        $redacted = [];

        foreach ($row as $column => $value) {
            $key = (string) $column;

            if ($this->isPiiKey($key)) {
                // Key-based redaction: the whole value goes, whatever it is.
                $redacted[$key] = self::PLACEHOLDER;

                continue;
            }

            // Pattern-based redaction for free-text values that may embed PII.
            $redacted[$key] = $this->redactValue($value);
        }

        return $redacted;
    }

    /**
     * Whether a column name denotes PII and should be redacted by key.
     *
     * A column matches when one of its lowercased alphanumeric tokens equals a
     * known PII token — UNLESS the column clearly describes a safe
     * catalog/structure dimension (e.g. `product_name`), which is preserved so
     * grouping stays meaningful.
     */
    public function isPiiKey(string $column): bool
    {
        $normalized = strtolower($column);

        foreach (self::SAFE_KEY_PREFIXES as $safe) {
            if (str_starts_with($normalized, $safe)) {
                return false;
            }
        }

        $tokens = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (in_array($token, self::PII_KEY_TOKENS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask any PII-shaped substrings in a single value. Non-strings are
     * returned untouched (numbers/booleans/nulls are safe aggregates — R32.3).
     */
    public function redactValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $masked = preg_replace(self::EMAIL_PATTERN, self::PLACEHOLDER, $value) ?? $value;
        $masked = preg_replace(self::GSTIN_PATTERN, self::PLACEHOLDER, $masked) ?? $masked;
        $masked = preg_replace(self::PHONE_PATTERN, self::PLACEHOLDER, $masked) ?? $masked;

        return $masked;
    }
}
