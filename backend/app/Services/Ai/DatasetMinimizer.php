<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Reduces a report dataset to the MINIMUM aggregated/derived data required for
 * a summary or follow-up, BEFORE it is handed to the {@see PiiRedactor} and
 * transmitted to the language model (R32.3).
 *
 * The design's guardrail is "send only the minimum aggregated data required"
 * (design "AI Reporting Layer"). A raw report page can be hundreds of rows wide
 * with per-record detail; shipping all of it to a model is both wasteful and a
 * privacy risk. This class derives a compact aggregate profile the model can
 * reason over to produce takeaways, trends, and outliers (R32.1) WITHOUT seeing
 * the full row-level dataset:
 *
 *   - **shape** — row count and the column names present (structure only);
 *   - **numeric summaries** — for each numeric column, its count, sum, min,
 *     max, and average (the "aggregated/derived data" R32.3 permits);
 *   - **a bounded sample** — at most {@see MAX_SAMPLE_ROWS} representative rows
 *     (the top rows of the already-ordered page), so the model has concrete
 *     context for outliers without receiving the entire dataset.
 *
 * The minimizer never queries anything and never reaches outside the dataset it
 * is given, so a summary/follow-up derived from its output is computed SOLELY
 * from the returned dataset (R32.1, R32.2). It is pure and stateless.
 */
class DatasetMinimizer
{
    /**
     * The largest number of representative sample rows included in the
     * minimized payload. Keeps the data sent to the model bounded (R32.3).
     */
    public const MAX_SAMPLE_ROWS = 20;

    /**
     * Build the minimized aggregate payload for a dataset.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     row_count: int,
     *     columns: list<string>,
     *     numeric_summaries: array<string, array{count:int,sum:float,min:float,max:float,avg:float}>,
     *     sample: list<array<string, mixed>>
     * }
     */
    public function minimize(array $rows): array
    {
        $rows = array_values($rows);

        return [
            'row_count' => count($rows),
            'columns' => $this->columns($rows),
            'numeric_summaries' => $this->numericSummaries($rows),
            'sample' => $this->sample($rows),
        ];
    }

    /**
     * The union of column names across the dataset, in first-seen order.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function columns(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $key = (string) $column;
                if (! in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
        }

        return $columns;
    }

    /**
     * Per-numeric-column aggregate summaries (count/sum/min/max/avg). Only
     * columns whose values are consistently numeric are summarised; a column
     * with any non-numeric value is treated as a dimension and skipped, so the
     * payload carries derived measures, not raw records (R32.3).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{count:int,sum:float,min:float,max:float,avg:float}>
     */
    private function numericSummaries(array $rows): array
    {
        /** @var array<string, list<float>> $byColumn */
        $byColumn = [];
        /** @var array<string, bool> $disqualified */
        $disqualified = [];

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $key = (string) $column;

                if (isset($disqualified[$key])) {
                    continue;
                }

                if ($this->isNumeric($value)) {
                    $byColumn[$key][] = (float) $value;
                } elseif ($value !== null) {
                    // A present non-numeric value marks this a dimension column.
                    $disqualified[$key] = true;
                    unset($byColumn[$key]);
                }
            }
        }

        $summaries = [];

        foreach ($byColumn as $column => $values) {
            if ($values === []) {
                continue;
            }

            $count = count($values);
            $sum = array_sum($values);

            $summaries[$column] = [
                'count' => $count,
                'sum' => $sum,
                'min' => min($values),
                'max' => max($values),
                'avg' => $sum / $count,
            ];
        }

        return $summaries;
    }

    /**
     * A bounded sample of the dataset's leading rows (R32.3). The report page is
     * already ordered by the spec's sort, so the leading rows are the most
     * salient (e.g. the largest values when sorted descending).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sample(array $rows): array
    {
        return array_slice($rows, 0, self::MAX_SAMPLE_ROWS);
    }

    /**
     * Whether a value is a number (int/float, or a numeric string that is not a
     * date-like token). Booleans are not treated as numeric measures.
     */
    private function isNumeric(mixed $value): bool
    {
        if (is_bool($value) || $value === null) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && is_numeric(trim($value));
    }
}
