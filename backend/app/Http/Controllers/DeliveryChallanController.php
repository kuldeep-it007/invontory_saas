<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryChallanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Delivery-challan printing endpoint (R33.4, R28.7).
 *
 * Thin controller: it resolves the {@see Order} from the route binding (404
 * when it does not exist in the active tenant database) and delegates the whole
 * challan assembly — party resolution, per-line totals, the invoice-number
 * challan reference, and the printable-PDF rendering — to
 * {@see DeliveryChallanService}. All business rules (including the 422 when the
 * order has not been dispatched/delivered) live in the service.
 *
 * One endpoint serves two renderings so a client can both display and PRINT the
 * challan (R33.4):
 *
 *   • GET /api/orders/{order}/challan            — the structured challan JSON
 *   • GET /api/orders/{order}/challan?format=pdf — a printable PDF download
 *     (also selected by an `Accept: application/pdf` request header)
 *
 * Runs on the tenant pipeline (authorized by `permission:orders.view`), so the
 * order and every record the challan references belong to the active tenant
 * database and the response is physically confined to that tenant (R28.10).
 */
class DeliveryChallanController extends Controller
{
    public function __construct(private readonly DeliveryChallanService $challans) {}

    /**
     * GET /api/orders/{order}/challan — render the order's delivery challan
     * (R33.4, R28.7).
     *
     * Returns a printable PDF download when `format=pdf` (or the request accepts
     * `application/pdf`), otherwise the structured challan JSON. The service
     * rejects an order that has not been dispatched/delivered with a 422.
     */
    public function show(Request $request, Order $order): JsonResponse|BinaryFileResponse
    {
        if ($this->wantsPdf($request)) {
            $path = $this->challans->renderPdf($order);

            return response()
                ->download($path, $this->challans->downloadName($order), [
                    'Content-Type' => 'application/pdf',
                ])
                ->deleteFileAfterSend(true);
        }

        return response()->json([
            'data' => $this->challans->build($order),
        ]);
    }

    /**
     * Whether the caller asked for the printable PDF rendering — either via the
     * explicit `format=pdf` query parameter or an `Accept: application/pdf`
     * request header.
     */
    private function wantsPdf(Request $request): bool
    {
        if (strtolower((string) $request->query('format', '')) === 'pdf') {
            return true;
        }

        return str_contains(strtolower((string) $request->header('Accept', '')), 'application/pdf');
    }
}
