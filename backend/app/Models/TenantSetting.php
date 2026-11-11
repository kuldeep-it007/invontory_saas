<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Services\WhiteLabelService;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-owned key/value setting (tenant database `tenant_settings` table).
 *
 * Backs per-tenant white-label and operational settings (logo, brand name,
 * colour theme, favicon, custom domain, outbound email sender identity) stored
 * as a flexible key/value store inside the tenant's own database (R3.1). Each
 * row is one setting keyed by `key` (UNIQUE within the tenant database); the
 * {@see WhiteLabelService} composes/decomposes these rows into a
 * coherent white-label settings object.
 *
 * Because isolation is physical (one database per tenant) there is no
 * `tenant_id` column — a row written on the active tenant connection belongs to
 * that tenant automatically.
 *
 * The model is {@see TenantAuditable} so every create/update/delete is recorded
 * in the tenant `audit_logs` table (R34.1). That audit write is best-effort:
 * {@see WhiteLabelService} applies updates regardless of whether
 * the audit write succeeds (R3.5).
 */
class TenantSetting extends Model implements Auditable
{
    use TenantAuditable;

    protected $table = 'tenant_settings';

    protected $fillable = ['key', 'value'];
}
