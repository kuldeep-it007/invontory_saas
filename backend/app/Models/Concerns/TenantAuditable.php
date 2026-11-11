<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AuditLog;
use OwenIt\Auditing\Auditable as OwenItAuditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Marks a tenant-owned Eloquent model as auditable.
 *
 * Apply this to every tenant-owned model whose create/update/delete must be
 * recorded in the tenant `audit_logs` table with actor, timestamp, and the
 * affected record identity (R34.1). It is the single pattern/base for auditing
 * tenant-owned models — User and Company today, and all future tenant-owned
 * models (products, orders, payments, etc.) as they are introduced.
 *
 * Usage:
 *
 *   use App\Models\Concerns\TenantAuditable;
 *   use OwenIt\Auditing\Contracts\Auditable;
 *
 *   class Company extends Model implements Auditable
 *   {
 *       use TenantAuditable;
 *   }
 *
 * The trait pulls in owen-it/laravel-auditing's {@see OwenItAuditable} behaviour
 * and re-exports the {@see AuditableContract} interface it satisfies, so a model
 * only needs to `use TenantAuditable` and declare it implements `Auditable`.
 * Audits are written through {@see AuditLog} (configured as the
 * audit implementation), which enforces the append-only guarantee (R34.2).
 *
 * This trait deliberately adds no `tenant_id` scoping: tenant isolation is
 * physical (one database per tenant), so audits land in the active tenant's
 * database automatically.
 */
trait TenantAuditable
{
    use OwenItAuditable;

    /**
     * Interface assertion helper — any model using this trait is expected to
     * implement the owen-it {@see AuditableContract}. Kept as a doc-level
     * reminder; the contract is declared on the consuming model's class.
     *
     * @return class-string<AuditableContract>
     */
    public static function auditableContract(): string
    {
        return AuditableContract::class;
    }
}
