<?php

declare(strict_types=1);

namespace App\Reports\Definitions;

use App\Reports\ReportDefinition;
use App\Services\ReportQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Shared base for concrete report definitions.
 *
 * Provides the two things nearly every report needs and nothing it does not:
 *
 *   - a {@see table()} helper that always starts the base query on the ACTIVE
 *     TENANT connection, so every report is physically confined to the current
 *     tenant's database (R28.10); and
 *   - a {@see sortExpression()} default that maps an allow-listed sort key
 *     straight to its real SQL column via a per-definition map, failing loudly
 *     if a definition forgets to map an advertised sortable column (a
 *     programming error, not a caller error).
 *
 * Concrete definitions remain small — they declare their type, their
 * allow-listed grouping/sort keys, and build their indexed, LEFT-JOINed,
 * null-coalesced base query (R29.3, R29.7, R38.1).
 */
abstract class AbstractReportDefinition implements ReportDefinition
{
    /**
     * Map of allow-listed sort KEY → real SQL column/expression. Concrete
     * definitions override this so {@see sortExpression()} can resolve a
     * validated sort key without exposing arbitrary columns.
     *
     * @return array<string, string>
     */
    protected function sortColumnMap(): array
    {
        return [];
    }

    /**
     * Map of allow-listed grouping KEY → real SQL column/expression. Concrete
     * definitions override this so {@see applyGrouping()} can resolve validated
     * grouping keys.
     *
     * @return array<string, string>
     */
    protected function groupingColumnMap(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * Default grouping: GROUP BY each validated key's mapped SQL column. The
     * keys passed here are already validated against {@see groupableColumns()}
     * by the service, so an unmapped key is a definition bug, not a caller
     * error.
     */
    public function applyGrouping(Builder $query, array $grouping): void
    {
        $map = $this->groupingColumnMap();

        foreach ($grouping as $key) {
            if (! isset($map[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Report [%s] advertises grouping key [%s] but does not map it to a column.',
                    $this->type()->value,
                    $key,
                ));
            }

            $query->groupBy($map[$key]);
        }
    }

    /**
     * {@inheritDoc}
     *
     * Resolve a validated sort KEY to its real SQL column via the per-definition
     * map. The key is already validated against {@see sortableColumns()} by the
     * service.
     */
    public function sortExpression(string $column): string
    {
        $map = $this->sortColumnMap();

        if (! isset($map[$column])) {
            throw new InvalidArgumentException(sprintf(
                'Report [%s] advertises sortable column [%s] but does not map it to a SQL column.',
                $this->type()->value,
                $column,
            ));
        }

        return $map[$column];
    }

    /**
     * Start a base query on the active tenant connection against `$table`. Every
     * report query begins here, so tenant scoping is physical (R28.10).
     */
    protected function table(string $table): Builder
    {
        return DB::connection(ReportQueryService::CONNECTION)->table($table);
    }
}
