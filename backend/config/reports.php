<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Report Export
    |--------------------------------------------------------------------------
    |
    | Settings for the report export + scheduling half of the Report_Engine
    | (R29.4, R29.5, R29.6).
    |
    | async_threshold: the result row count at or above which an export is
    | treated as "large" and processed as an asynchronous queued streaming job
    | that provides a download link on completion (R29.5). Below it, an export
    | is produced inline for an immediate download link.
    |
    */

    'export' => [
        'async_threshold' => (int) env('REPORT_EXPORT_ASYNC_THRESHOLD', 1000),
    ],

];
