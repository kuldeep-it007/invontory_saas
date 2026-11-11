<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\ProspectController;
use App\Mail\AccountCreatedMail;
use App\Models\Concerns\TenantAuditable;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Notification;
use App\Models\Prospect;
use App\Support\AuditRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Focused service for the sign-up and prospect approval flow (R9.1–R9.4).
 *
 * Owns the business rules the thin {@see ProspectController}
 * delegates to:
 *
 *   • {@see signUp()} records a prospective buyer with contact details and a
 *     `pending` status (R9.1).
 *   • {@see approve()} creates the corresponding Distributor or Dealer —
 *     delegating to {@see DistributorService}/{@see DealerService} so quota and
 *     `max_dealers_per_distributor` are enforced in exactly one place (R10.4) —
 *     records the approval in the audit log (R9.2), and queues an
 *     account-created email (R9.4).
 *   • {@see reject()} marks the prospect `rejected` and records the rejection in
 *     the audit log (R9.3).
 *
 * All work runs on the active tenant connection (resolved by the tenancy
 * middleware), so records always belong to the current tenant (physical
 * isolation). The {@see Prospect} create/update are audited automatically via
 * its {@see TenantAuditable} trait; approval/rejection are
 * additionally recorded as explicit security-relevant events through
 * {@see AuditRecorder} (R9.2, R9.3, R34.3).
 */
class ProspectService
{
    public function __construct(
        private readonly DistributorService $distributors,
        private readonly DealerService $dealers,
        private readonly WhiteLabelService $whiteLabel,
        private readonly AuditRecorder $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Record a self sign-up as a `pending` prospect (R9.1).
     *
     * @param  array<string, mixed>  $data  Validated prospect attributes.
     */
    public function signUp(array $data): Prospect
    {
        return DB::connection('tenant')->transaction(function () use ($data): Prospect {
            return Prospect::create([
                'name' => $data['name'],
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'gstin' => $data['gstin'] ?? null,
                'address' => $data['address'] ?? null,
                'intended_buyer_type' => $data['intended_buyer_type'],
                'serving_distributor_id' => $data['serving_distributor_id'] ?? null,
                'status' => Prospect::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Approve a pending prospect: create the corresponding Distributor or Dealer
     * (delegating so quota + per-distributor caps are enforced), record the
     * approval in the audit log, and queue an account-created email (R9.2, R9.4).
     *
     * @return Distributor|Dealer The newly created buyer.
     *
     * @throws ConflictHttpException When the prospect is not pending (409).
     * @throws UnprocessableEntityHttpException When a dealer prospect has no
     *                                          resolvable serving Distributor (422).
     */
    public function approve(Prospect $prospect): Distributor|Dealer
    {
        $this->assertPending($prospect);

        $buyer = DB::connection('tenant')->transaction(function () use ($prospect): Distributor|Dealer {
            $buyer = $prospect->isDealer()
                ? $this->createDealer($prospect)
                : $this->createDistributor($prospect);

            $prospect->status = Prospect::STATUS_APPROVED;
            $prospect->save();

            return $buyer;
        });

        // Record the approval as an explicit audit event (R9.2). Best-effort via
        // AuditRecorder; the approval itself has already been committed.
        $this->audit->record('prospect_approved', auth()->user(), [
            'prospect_id' => $prospect->getKey(),
            'intended_buyer_type' => $prospect->intended_buyer_type,
            'buyer_type' => $buyer::class,
            'buyer_id' => $buyer->getKey(),
        ]);

        // Queue the account-created email using the tenant's sender identity
        // (R9.4, R27.1, R3.3). Queued so the mail send never blocks approval.
        $this->sendAccountCreatedEmail($prospect, $buyer);

        // Record the in-app notification mirror of the account-created event
        // (R27.6) and, where enabled, the optional SMS/WhatsApp channels
        // (R27.5). Best-effort, after the approval has already committed, so it
        // never blocks or fails the approval.
        $this->recordAccountCreatedNotification($prospect, $buyer);

        return $buyer;
    }

    /**
     * Reject a pending prospect: mark it `rejected` and record the rejection in
     * the audit log (R9.3).
     *
     * @throws ConflictHttpException When the prospect is not pending (409).
     */
    public function reject(Prospect $prospect): Prospect
    {
        $this->assertPending($prospect);

        $prospect = DB::connection('tenant')->transaction(function () use ($prospect): Prospect {
            $prospect->status = Prospect::STATUS_REJECTED;
            $prospect->save();

            return $prospect->refresh();
        });

        $this->audit->record('prospect_rejected', auth()->user(), [
            'prospect_id' => $prospect->getKey(),
            'intended_buyer_type' => $prospect->intended_buyer_type,
        ]);

        return $prospect;
    }

    /**
     * Create a Distributor from an approved distributor prospect, delegating to
     * {@see DistributorService} so the `max_distributors` quota is enforced.
     */
    private function createDistributor(Prospect $prospect): Distributor
    {
        return $this->distributors->create($this->buyerAttributes($prospect));
    }

    /**
     * Create a Dealer from an approved dealer prospect, delegating to
     * {@see DealerService} so the `max_dealers` quota and the per-distributor
     * `max_dealers_per_distributor` cap are enforced (R10.4).
     *
     * @throws UnprocessableEntityHttpException When the serving Distributor cannot be resolved.
     */
    private function createDealer(Prospect $prospect): Dealer
    {
        $distributor = $this->resolveServingDistributor($prospect);

        return $this->dealers->create($distributor, $this->buyerAttributes($prospect));
    }

    /**
     * Resolve the Distributor a dealer prospect will be served by (R20.2). A
     * dealer prospect must name a serving Distributor before approval.
     *
     * @throws UnprocessableEntityHttpException When no serving Distributor is set/resolvable.
     */
    private function resolveServingDistributor(Prospect $prospect): Distributor
    {
        if ($prospect->serving_distributor_id === null) {
            throw new UnprocessableEntityHttpException(
                'A dealer prospect must specify a serving distributor before approval.',
            );
        }

        try {
            return Distributor::query()->findOrFail($prospect->serving_distributor_id);
        } catch (ModelNotFoundException $e) {
            throw new UnprocessableEntityHttpException(
                'The serving distributor for this dealer prospect no longer exists.',
                $e,
            );
        }
    }

    /**
     * Map a prospect's contact details onto the buyer-creation payload shared by
     * DistributorService/DealerService.
     *
     * @return array<string, mixed>
     */
    private function buyerAttributes(Prospect $prospect): array
    {
        return [
            'name' => $prospect->name,
            'contact_name' => $prospect->contact_name,
            'contact_email' => $prospect->contact_email,
            'contact_phone' => $prospect->contact_phone,
            'gstin' => $prospect->gstin,
            'address' => $prospect->address,
        ];
    }

    /**
     * Queue the account-created email to the new buyer's contact address using
     * the active tenant's sender identity (R9.4, R3.3).
     */
    private function sendAccountCreatedEmail(Prospect $prospect, Distributor|Dealer $buyer): void
    {
        if (empty($prospect->contact_email)) {
            return;
        }

        $brandName = $this->whiteLabel->get()['brand_name'] ?? null;

        Mail::to($prospect->contact_email)->queue(new AccountCreatedMail(
            accountName: (string) $buyer->name,
            buyerType: $prospect->intended_buyer_type,
            brandName: $brandName,
        ));
    }

    /**
     * Record the in-app account-created notification for the approving admin,
     * and offer the optional SMS/WhatsApp channels to the new buyer's phone
     * where the `whatsapp_notifications` flag is enabled (R27.1, R27.5, R27.6).
     * Best-effort: a failure here never undoes the already-committed approval.
     */
    private function recordAccountCreatedNotification(Prospect $prospect, Distributor|Dealer $buyer): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $this->notifications->notify(
            userId: (int) $userId,
            type: Notification::TYPE_ACCOUNT_CREATED,
            title: 'Account created',
            body: sprintf('%s account for "%s" has been created.', ucfirst($prospect->intended_buyer_type), (string) $buyer->name),
            data: [
                'buyer_type' => $prospect->intended_buyer_type,
                'buyer_id' => (int) $buyer->getKey(),
            ],
            phone: $buyer->contact_phone,
        );
    }

    /**
     * Guard: approval/rejection only act on a pending prospect (R9.1–R9.3).
     *
     * @throws ConflictHttpException When the prospect is not pending (409).
     */
    private function assertPending(Prospect $prospect): void
    {
        if (! $prospect->isPending()) {
            throw new ConflictHttpException(
                "Prospect {$prospect->getKey()} is not pending (status: {$prospect->status}).",
            );
        }
    }
}
