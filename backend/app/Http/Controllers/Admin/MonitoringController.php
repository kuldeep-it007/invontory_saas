<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Services\Monitoring\MonitorStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Platform_Admin monitoring dashboard: live server/application metrics, trends,
 * alerts, thresholds, errors and deployment history. All data comes from the
 * host agent's files (see MonitorStore); nothing here is synthetic.
 */
class MonitoringController extends Controller
{
    public function __construct(private readonly MonitorStore $store) {}

    /** GET /api/admin/monitoring/overview */
    public function overview(): JsonResponse
    {
        $deployments = $this->store->deployments(1);

        return response()->json(['data' => [
            'current' => $this->store->current(),
            'alerts' => $this->store->alerts(20),
            'latest_deployment' => $deployments[0] ?? null,
            'app' => ['name' => config('app.name'), 'env' => config('app.env')],
        ]]);
    }

    /** GET /api/admin/monitoring/metrics?range=24h */
    public function metrics(Request $request): JsonResponse
    {
        $data = $request->validate(['range' => ['required', Rule::in(array_keys(MonitorStore::RANGES))]]);

        return response()->json(['data' => $this->store->series($data['range'])]);
    }

    /** GET /api/admin/monitoring/alerts */
    public function alerts(): JsonResponse
    {
        return response()->json(['data' => $this->store->alerts(100)]);
    }

    /** GET /api/admin/monitoring/thresholds */
    public function thresholds(): JsonResponse
    {
        return response()->json(['data' => $this->store->thresholds()]);
    }

    /** PUT /api/admin/monitoring/thresholds */
    public function updateThresholds(Request $request): JsonResponse
    {
        $input = $request->validate([
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*.warning' => ['required', 'numeric'],
            'thresholds.*.critical' => ['required', 'numeric'],
        ])['thresholds'];
        $old = $this->store->thresholds();
        try {
            $saved = $this->store->saveThresholds($input);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['thresholds' => $e->getMessage()]);
        }
        PlatformAuditLog::record('monitoring.thresholds_updated', $request, null, $old, $saved);

        return response()->json(['data' => $saved]);
    }

    /** GET /api/admin/monitoring/errors */
    public function errors(): JsonResponse
    {
        return response()->json(['data' => $this->store->recentErrors()]);
    }

    /** GET /api/admin/monitoring/deployments */
    public function deployments(): JsonResponse
    {
        $rows = $this->store->deployments(100);
        $success = array_values(array_filter($rows, fn (array $r): bool => ($r['status'] ?? '') === 'success'));

        return response()->json(['data' => [
            'deployments' => $rows,
            'rollbacks' => array_values(array_filter($rows, fn (array $r): bool => ($r['rollback']['performed'] ?? false) === true)),
            'last_successful' => array_slice($success, 0, 5),
        ]]);
    }

    /** GET /api/admin/monitoring/deployments/{id} */
    public function deployment(string $id): JsonResponse
    {
        $detail = $this->store->deployment($id);
        abort_if($detail === null, 404, 'Deployment not found.');

        return response()->json(['data' => $detail]);
    }
}
