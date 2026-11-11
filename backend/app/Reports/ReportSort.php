<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * One ordering directive within a {@see ReportQuerySpec}: a column plus a
 * direction (design "Reporting Engine" — the `sort: { column, dir }[]` field).
 *
 * The `column` is an ALLOW-LISTED report column — it is validated against the
 * executing report's sortable allow-list before it is ever placed into an
 * `ORDER BY`, so a caller (including the AI layer) can never sort by an
 * arbitrary expression (R31.2). The direction is normalized to the lowercase
 * `asc`/`desc` vocabulary on construction; anything else falls back to `asc`.
 */
final class ReportSort
{
    public const ASC = 'asc';

    public const DESC = 'desc';

    public readonly string $direction;

    public function __construct(
        public readonly string $column,
        string $direction = self::ASC,
    ) {
        $normalized = strtolower(trim($direction));

        $this->direction = $normalized === self::DESC ? self::DESC : self::ASC;
    }

    /**
     * Whether this directive sorts descending.
     */
    public function isDescending(): bool
    {
        return $this->direction === self::DESC;
    }
}
