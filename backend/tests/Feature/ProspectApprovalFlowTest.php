<?php

declare(strict_types=1);

use App\Mail\AccountCreatedMail;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Plan;
use App\Models\Prospect;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\DistributorService;
use App\Services\ProspectService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/*
|--------------------------------------------------------------------------
| Task 7.3 — Prospect approve/reject flows (R9.1–R9.4)
|--------------------------------------------------------------------------
|
| Exercises ProspectService against a real provisioned tenant database:
|   • sign-up records a prospect as `pending` (R9.1);
|   • approving a distributor prospect creates a Distributor, records an
|     explicit `prospect_approved` audit entry, and queues the account-created
|     email (R9.2, R9.4);
|   • approving a dealer prospect creates a Dealer under its serving
|     distributor + audit + email (R9.2, R9.4);
|   • rejecting a prospect marks it `rejected` and records a
|     `prospect_rejected` audit entry (R9.3);
|   • approve/reject only act on a pending prospect (R9.1–R9.3).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE for stancl tenancy) and
| skips gracefully when MySQL is unreachable, mirroring the management suite.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision a plan-backed tenant for prospect tests, returning it.
 */
function provisionProspectTenant(string $suffix, ?Plan &$plan = null): Tenant
{
    $plan = Plan::create([
        'name' => 'Prospect '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $result = app(App\Services\TenantProvisioningService::class)->provision([
        'subdomain' => 'prospect-'.$suffix,
        'plan_id' => $plan->getKey(),
        'currency' => 'USD',
        'company_name' => 'Prospect Co '.$suffix,
        'admin_name' => 'Prospect Admin '.$suffix,
        'admin_email' => 'prospect-admin-'.$suffix.'@example.test',
        'admin_password' => 'password123',
    ]);

    return $result['tenant'];
}

function teardownProspectTenant(?Tenant $tenant, ?Plan $plan): void
{
    if ($tenant !== null && $tenant->exists) {
        try {
            Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
            $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $tenant->domains()->delete();
            $tenant->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }

    $plan?->delete();
}

it('records a self sign-up as a pending prospect (R9.1)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionProspectTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            $prospect = app(ProspectService::class)->signUp([
                'name' => 'Hopeful Distributor',
                'contact_name' => 'Pat Prospect',
                'contact_email' => 'pat@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            // The prospect is captured as pending (R9.1).
            expect($prospect->status)->toBe(Prospect::STATUS_PENDING);
            expect($prospect->isPending())->toBeTrue();

            $fresh = Prospect::query()->findOrFail($prospect->getKey());
            expect($fresh->status)->toBe(Prospect::STATUS_PENDING);
            expect($fresh->name)->toBe('Hopeful Distributor');
            expect($fresh->contact_email)->toBe('pat@example.test');

            // No buyer exists yet — approval has not happened.
            expect(Distributor::query()->count())->toBe(0);
            expect(Dealer::query()->count())->toBe(0);
        });
    } finally {
        teardownProspectTenant($tenant, $plan);
    }
});

it('approves a distributor prospect: creates a Distributor + audit + queued email (R9.2, R9.4)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionProspectTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            Mail::fake();
            // Enable console auditing so the model-mutation audits fire under the
            // test runner, alongside the explicit AuditRecorder event (R34.1).
            Config::set('audit.console', true);

            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Acme Prospect',
                'contact_email' => 'acme@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            $buyer = $service->approve($prospect);

            // A Distributor was created from the prospect (R9.2).
            expect($buyer)->toBeInstanceOf(Distributor::class);
            expect($buyer->name)->toBe('Acme Prospect');
            expect($buyer->contact_email)->toBe('acme@example.test');
            expect(Distributor::query()->count())->toBe(1);

            // The prospect is now approved (R9.2).
            expect($prospect->refresh()->status)->toBe(Prospect::STATUS_APPROVED);

            // The approval is recorded as an explicit audit event (R9.2).
            $approvalAudits = AuditLog::query()
                ->where('event', 'prospect_approved')
                ->get();
            expect($approvalAudits)->toHaveCount(1);
            expect($approvalAudits->first()->new_values['prospect_id'])->toBe($prospect->getKey());

            // The account-created email is queued to the prospect's contact (R9.4).
            Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
                return $mail->hasTo('acme@example.test')
                    && $mail->buyerType === Prospect::TYPE_DISTRIBUTOR
                    && $mail->accountName === 'Acme Prospect';
            });
        });
    } finally {
        teardownProspectTenant($tenant, $plan);
    }
});

it('approves a dealer prospect: creates a Dealer under its serving distributor + audit + email (R9.2, R9.4)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionProspectTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            Mail::fake();
            Config::set('audit.console', true);

            // A serving distributor must exist for a dealer prospect (R20.2).
            $distributor = app(DistributorService::class)->create(['name' => 'Serving Distributor']);

            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Dealer Prospect',
                'contact_email' => 'dealer@example.test',
                'intended_buyer_type' => Prospect::TYPE_DEALER,
                'serving_distributor_id' => $distributor->getKey(),
            ]);

            $buyer = $service->approve($prospect);

            // A Dealer was created under the serving distributor (R9.2).
            expect($buyer)->toBeInstanceOf(Dealer::class);
            expect($buyer->distributor_id)->toBe($distributor->getKey());
            expect(Dealer::query()->count())->toBe(1);

            expect($prospect->refresh()->status)->toBe(Prospect::STATUS_APPROVED);

            // Explicit approval audit event names the dealer buyer type (R9.2).
            $audit = AuditLog::query()->where('event', 'prospect_approved')->first();
            expect($audit)->not->toBeNull();
            expect($audit->new_values['intended_buyer_type'])->toBe(Prospect::TYPE_DEALER);

            // The account-created email is queued for the dealer (R9.4).
            Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
                return $mail->hasTo('dealer@example.test')
                    && $mail->buyerType === Prospect::TYPE_DEALER;
            });
        });
    } finally {
        teardownProspectTenant($tenant, $plan);
    }
});

it('rejects a pending prospect: marks it rejected + audit, no buyer or email (R9.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionProspectTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            Mail::fake();
            Config::set('audit.console', true);

            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Rejected Prospect',
                'contact_email' => 'reject@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            $rejected = $service->reject($prospect);

            // The prospect is marked rejected (R9.3).
            expect($rejected->status)->toBe(Prospect::STATUS_REJECTED);
            expect(Prospect::query()->findOrFail($prospect->getKey())->status)
                ->toBe(Prospect::STATUS_REJECTED);

            // Rejection recorded as an explicit audit event (R9.3).
            $audit = AuditLog::query()->where('event', 'prospect_rejected')->first();
            expect($audit)->not->toBeNull();
            expect($audit->new_values['prospect_id'])->toBe($prospect->getKey());

            // No buyer is created and no account-created email is sent on reject.
            expect(Distributor::query()->count())->toBe(0);
            expect(Dealer::query()->count())->toBe(0);
            Mail::assertNothingQueued();
        });
    } finally {
        teardownProspectTenant($tenant, $plan);
    }
});

it('only approves or rejects a pending prospect (R9.1–R9.3)', function () {
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $plan = null;
    $tenant = provisionProspectTenant($suffix, $plan);

    try {
        $tenant->run(function () {
            Mail::fake();

            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Once Decided',
                'contact_email' => 'once@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            // First rejection succeeds and leaves it non-pending.
            $service->reject($prospect);
            expect($prospect->refresh()->status)->toBe(Prospect::STATUS_REJECTED);

            // A second decision on the now-rejected prospect is refused (409).
            $approveThrown = null;
            try {
                $service->approve($prospect);
            } catch (ConflictHttpException $e) {
                $approveThrown = $e;
            }
            expect($approveThrown)->toBeInstanceOf(ConflictHttpException::class);
            expect($approveThrown->getStatusCode())->toBe(409);

            $rejectThrown = null;
            try {
                $service->reject($prospect);
            } catch (ConflictHttpException $e) {
                $rejectThrown = $e;
            }
            expect($rejectThrown)->toBeInstanceOf(ConflictHttpException::class);

            // No buyer was created by the refused approval.
            expect(Distributor::query()->count())->toBe(0);
        });
    } finally {
        teardownProspectTenant($tenant, $plan);
    }
});
