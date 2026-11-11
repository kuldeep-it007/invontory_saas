<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Reports\Export\PrintablePdfReportWriter;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Delivery-challan generation/printing for Orders (R33.4, R28.7).
 *
 * A delivery challan is the document that accompanies goods leaving the
 * seller — it is meaningful only once an order has actually been dispatched or
 * delivered (R21.1 lifecycle). This service builds a structured challan for
 * such an order (header: challan reference, parties, dates; body: one row per
 * order line with product, quantity, unit price, discount, and line total) and
 * can render that challan to a printable PDF.
 *
 * It reuses the invoice number as the challan reference (the same reference the
 * order-status / delivery-challan listing report surfaces for dispatched/
 * delivered orders — see {@see \App\Reports\Definitions\OrderStatusReportDefinition}),
 * and reuses the existing {@see PrintablePdfReportWriter} so printing produces a
 * genuine `application/pdf` document without pulling in a heavyweight PDF engine
 * (R29.4 shares the same printable-PDF approach).
 *
 * Everything runs against the active tenant database (physical isolation): the
 * order, its lines, its invoice, and the referenced products all resolve on the
 * tenant connection, so a challan can never reference another tenant's data
 * (R28.10).
 */
class DeliveryChallanService
{
    /**
     * The lifecycle states for which a delivery challan is meaningful: the
     * order's goods must have left the seller (R21.1). Printing a challan for a
     * draft/placed/confirmed/cancelled order is rejected with a 422.
     *
     * @var list<string>
     */
    private const PRINTABLE_STATES = [
        OrderStatus::DISPATCHED,
        OrderStatus::DELIVERED,
        OrderStatus::CLOSED,
    ];

    public function __construct(private readonly PrintablePdfReportWriter $pdf) {}

    /**
     * Build the structured delivery challan for a dispatched/delivered order
     * (R33.4, R28.7).
     *
     * @return array{
     *     reference: ?string,
     *     order: array{id:int,status:string,order_type:string,total:string,placed_at:?string},
     *     seller: array{type:string,id:int,name:?string},
     *     buyer: array{type:string,id:int,name:?string},
     *     lines: list<array{product_id:int,sku:?string,name:?string,quantity:string,unit_price:string,discount:string,line_total:string}>,
     *     line_count: int,
     *     total_quantity: string,
     * }
     *
     * @throws UnprocessableEntityHttpException when the order has not been dispatched/delivered.
     */
    public function build(Order $order): array
    {
        $this->assertPrintable($order);

        $order->loadMissing(['lines', 'buyer', 'seller']);

        $invoice = Invoice::query()
            ->where('order_id', (int) $order->getKey())
            ->first();

        $lines = [];
        $totalQuantity = '0.000';

        foreach ($order->lines as $line) {
            $lineTotal = $this->lineTotal(
                (string) $line->quantity,
                (string) $line->unit_price,
                (string) $line->discount,
            );

            $product = Product::query()
                ->select(['id', 'sku', 'name'])
                ->find((int) $line->product_id);

            $lines[] = [
                'product_id' => (int) $line->product_id,
                'sku' => $product?->sku,
                'name' => $product?->name,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount' => (string) $line->discount,
                'line_total' => $lineTotal,
            ];

            $totalQuantity = $this->addQuantity($totalQuantity, (string) $line->quantity);
        }

        return [
            'reference' => $this->reference($invoice),
            'order' => [
                'id' => (int) $order->getKey(),
                'status' => (string) $order->status,
                'order_type' => (string) $order->order_type,
                'total' => (string) $order->total,
                'placed_at' => $order->created_at?->toIso8601String(),
            ],
            'seller' => [
                'type' => (string) $order->seller_type,
                'id' => (int) $order->seller_id,
                'name' => $this->partyName($order->seller),
            ],
            'buyer' => [
                'type' => (string) $order->buyer_type,
                'id' => (int) $order->buyer_id,
                'name' => $this->partyName($order->buyer),
            ],
            'lines' => $lines,
            'line_count' => count($lines),
            'total_quantity' => $totalQuantity,
        ];
    }

    /**
     * Render the delivery challan to a printable PDF and return the absolute
     * path of the written file (R33.4). The caller streams/downloads the file
     * and is responsible for cleaning it up.
     *
     * The challan's line rows become the PDF's table body (reusing the shared
     * {@see PrintablePdfReportWriter}); the header/parties ride along in the
     * document meta so they print above the table.
     *
     * @throws UnprocessableEntityHttpException when the order has not been dispatched/delivered.
     */
    public function renderPdf(Order $order): string
    {
        $challan = $this->build($order);

        $path = $this->temporaryPath((int) $order->getKey());

        $this->pdf->write($challan['lines'], $path, [
            'title' => $this->documentTitle($challan),
            'generated_at' => now()->toIso8601String(),
        ]);

        return $path;
    }

    /**
     * A download filename for the rendered challan (R33.4).
     */
    public function downloadName(Order $order): string
    {
        $invoice = Invoice::query()
            ->where('order_id', (int) $order->getKey())
            ->first();

        $reference = $this->reference($invoice) ?? ('order-'.$order->getKey());
        $reference = preg_replace('/[^A-Za-z0-9._-]+/', '-', $reference) ?? 'challan';

        return 'delivery-challan-'.$reference.'.pdf';
    }

    /**
     * Reject printing a challan for an order whose goods have not left the
     * seller (R21.1). A challan is a dispatch/delivery document, so it is only
     * meaningful once the order is dispatched, delivered, or closed.
     *
     * @throws UnprocessableEntityHttpException
     */
    private function assertPrintable(Order $order): void
    {
        if (! in_array($order->status, self::PRINTABLE_STATES, true)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'A delivery challan can only be printed for a dispatched or delivered order; order #%d is %s.',
                (int) $order->getKey(),
                (string) $order->status,
            ));
        }
    }

    /**
     * The challan reference: the order's invoice number within its seller
     * series, mirroring the order-status/delivery-challan listing report
     * (R28.7). Null when the order has no invoice (should not occur for a
     * dispatched order, but handled defensively).
     */
    private function reference(?Invoice $invoice): ?string
    {
        if ($invoice === null) {
            return null;
        }

        return $invoice->series.'/'.$invoice->number;
    }

    /**
     * The per-line amount: quantity × unit_price − discount, clamped at zero,
     * computed with arbitrary-precision arithmetic so monetary values are never
     * silently coerced to float (R39.1).
     */
    private function lineTotal(string $quantity, string $unitPrice, string $discount): string
    {
        if (function_exists('bcmul')) {
            $gross = bcmul($quantity, $unitPrice, 2);
            $net = bcsub($gross, $discount, 2);

            return bccomp($net, '0.00', 2) < 0 ? '0.00' : $net;
        }

        // Float fallback when the bcmath extension is unavailable; the result is
        // formatted back to a 2-dp string so the challan still renders.
        $net = ((float) $quantity * (float) $unitPrice) - (float) $discount;

        return number_format(max(0.0, $net), 2, '.', '');
    }

    /**
     * Sum two DECIMAL(15,3) quantity strings with arbitrary precision, falling
     * back to float arithmetic when the bcmath extension is unavailable.
     */
    private function addQuantity(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 3);
        }

        return number_format((float) $a + (float) $b, 3, '.', '');
    }

    /**
     * The display name of a polymorphic party (Company/Distributor/Dealer),
     * when it resolves. Null when the related record is missing/soft-deleted so
     * the challan still renders (R29.7-style robustness).
     */
    private function partyName(mixed $party): ?string
    {
        if ($party === null) {
            return null;
        }

        $name = $party->getAttribute('name');

        return $name === null ? null : (string) $name;
    }

    /**
     * The printable document's title line, carrying the challan reference and
     * the order id so the printed sheet identifies the shipment.
     *
     * @param  array{reference:?string,order:array{id:int}}  $challan
     */
    private function documentTitle(array $challan): string
    {
        $reference = $challan['reference'] ?? ('Order #'.$challan['order']['id']);

        return 'Delivery Challan  '.$reference;
    }

    /**
     * A unique temporary absolute path for the rendered PDF under the storage
     * tmp directory, created on demand.
     */
    private function temporaryPath(int $orderId): string
    {
        $dir = storage_path('app/tmp/challans');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        return $dir.DIRECTORY_SEPARATOR.sprintf(
            'challan-%d-%s.pdf',
            $orderId,
            bin2hex(random_bytes(4)),
        );
    }
}
