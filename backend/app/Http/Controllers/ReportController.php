<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use App\Services\ReportQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard report catalog HTTP surface: `GET /api/reports/{type}` (R28, R29).
 *
 * Thin controller: it resolves the requested report `type` against the
 * allow-listed {@see ReportType} enum, maps the request's query parameters to a
 * {@see ReportQuerySpec} (filters, grouping, sort, pagination), builds the
 * caller's {@see ReportAccessContext} from the authenticated principal, and
 * hands both to the single shared {@see ReportQueryService} — the one query
 * path every standard report and the AI layer run through (R29, R31.3). All
 * cross-cutting rules (previous-month default R29.2, tenant+role scoping
 * R28.10, allow-list validation R31.2, SQL-side indexed pagination R29.3) live
 * in the service, so this controller performs no business logic.
 *
 * It runs on the tenant pipeline (authorized by `permission:reports.view`), so
 * every report is physically confined to the active tenant database (R28.10),
 * and additionally the service confines a Distributor/Dealer principal to its
 * own account data.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportQueryService $reports) {}

    /**
     * GET /api/reports/{type} — run a standard report (R28.1–R28.9, R29).
     *
     * The `{type}` segment MUST be an allow-listed report type (R31.2);
     * anything else yields a 404 so an unknown report is never attempted. The
     * spec is assembled from the request query parameters:
     *
     *   - filters: from/to (date range), buyerType/buyerId, sellerType/sellerId,
     *     productId, categoryId, stockPointId, status (R29.1);
     *   - grouping[]: allow-listed grouping column keys;
     *   - sort[]: allow-listed { column, dir } directives;
     *   - limit / page: pagination (clamped to safe bounds in the spec).
     *
     * A grouping/sort column not on the report's allow-list is a 422 (surfaced
     * from the service's InvalidArgumentException), and a principal not
     * permitted to read the requested data is a 403 (R28.10).
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $reportType = ReportType::tryFromValue($type);

        if ($reportType === null || ! $this->reports->supports($reportType)) {
            abort(Response::HTTP_NOT_FOUND, sprintf('Unknown report type [%s].', $type));
        }

        $spec = ReportQuerySpec::fromArray([
            'type' => $reportType->value,
            'filters' => $this->filtersFrom($request),
            'grouping' => $this->groupingFrom($request),
            'sort' => $this->sortFrom($request),
            'limit' => $request->query('limit'),
            'page' => $request->query('page'),
        ]);

        $context = ReportAccessContext::fromUser($request->user());

        try {
            $result = $this->reports->run($spec, $context);
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        // Presentation only: show party TYPE and NAME instead of class names and ids.
        $payload = $result->toArray();
        $payload['data'] = $this->presentParties($payload['data'] ?? []);

        return response()->json($payload);
    }

    /**
     * Collect the report filters (R29.1) from the request query string. Absent
     * keys are omitted so {@see ReportFilters} treats them as unset (and the
     * service applies the previous-calendar-month default when no range is
     * given — R29.2).
     *
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request): array
    {
        return array_filter(
            [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'buyerType' => $request->query('buyer_type'),
                'buyerId' => $request->query('buyer_id'),
                'sellerType' => $request->query('seller_type'),
                'sellerId' => $request->query('seller_id'),
                'productId' => $request->query('product_id'),
                'categoryId' => $request->query('category_id'),
                'stockPointId' => $request->query('stock_point_id'),
                'status' => $request->query('status'),
            ],
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * The requested grouping column keys, normalized to a list of non-empty
     * strings. Validation against the report's allow-list happens in the
     * service (R31.2).
     *
     * @return list<string>
     */
    private function groupingFrom(Request $request): array
    {
        $grouping = $request->query('grouping', []);

        if (is_string($grouping)) {
            // Allow a comma-separated form (?grouping=period,buyer) in addition
            // to the array form (?grouping[]=period&grouping[]=buyer).
            $grouping = explode(',', $grouping);
        }

        $columns = [];
        foreach ((array) $grouping as $column) {
            $column = is_string($column) ? trim($column) : '';
            if ($column !== '') {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * The requested sort directives, normalized to a list of
     * `{ column, dir }` arrays the spec understands. Accepts either the
     * structured array form (?sort[0][column]=period&sort[0][dir]=desc) or a
     * compact string form (?sort=period:desc,buyer).
     *
     * @return list<array{column: string, dir: string}>
     */
    private function sortFrom(Request $request): array
    {
        $sort = $request->query('sort', []);

        $directives = [];

        if (is_string($sort)) {
            foreach (explode(',', $sort) as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }
                [$column, $dir] = array_pad(explode(':', $token, 2), 2, 'asc');
                $directives[] = ['column' => trim($column), 'dir' => trim($dir)];
            }

            return $directives;
        }

        foreach ((array) $sort as $directive) {
            if (is_array($directive) && isset($directive['column']) && $directive['column'] !== '') {
                $directives[] = [
                    'column' => (string) $directive['column'],
                    'dir' => (string) ($directive['dir'] ?? $directive['direction'] ?? 'asc'),
                ];
            }
        }

        return $directives;
    }

    /**
     * Make party columns human-readable for the report grid: a `<role>_type`
     * column (a model class name such as App\Models\Distributor) becomes its
     * short name ("Distributor"), and the paired `<role>_id` column is replaced
     * by the party's NAME under the key `<role>` (falling back to "#id").
     * Rows without such a pair are returned unchanged. The shared report
     * service, exports and AI layer are not affected.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function presentParties(array $rows): array
    {
        $roles = [];
        foreach (['buyer', 'seller'] as $role) {
            if ($rows !== [] && array_key_exists($role.'_type', $rows[0]) && array_key_exists($role.'_id', $rows[0])) {
                $roles[] = $role;
            }
        }
        if ($roles === []) {
            return $rows;
        }

        // Batch-resolve names: one query per party model, not per row.
        $wanted = [];
        foreach ($rows as $row) {
            foreach ($roles as $role) {
                if (is_string($row[$role.'_type'] ?? null) && is_numeric($row[$role.'_id'] ?? null)) {
                    $wanted[$row[$role.'_type']][(int) $row[$role.'_id']] = true;
                }
            }
        }

        $models = [Company::class, Distributor::class, Dealer::class];
        $names = [];
        foreach ($wanted as $class => $ids) {
            if (in_array($class, $models, true)) {
                $names[$class] = $class::query()->whereIn('id', array_keys($ids))->pluck('name', 'id')->all();
            }
        }

        return array_map(function (array $row) use ($roles, $names): array {
            $out = [];
            foreach ($row as $key => $value) {
                $role = null;
                foreach ($roles as $r) {
                    if ($key === $r.'_type' || $key === $r.'_id') {
                        $role = $r;
                    }
                }

                if ($role === null) {
                    $out[$key] = $value;
                } elseif ($key === $role.'_type') {
                    $out[$key] = is_string($value) ? class_basename($value) : $value;
                } else {
                    $type = $row[$role.'_type'] ?? null;
                    $out[$role] = $names[$type][(int) $value] ?? ($value === null ? null : '#'.$value);
                }
            }

            return $out;
        }, $rows);
    }}
