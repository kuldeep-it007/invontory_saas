<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Central-database Domain model (stancl/tenancy).
 *
 * Maps a subdomain or custom domain to a {@see Tenant}. This is the resolution
 * table consulted on every tenant request to identify the tenant and switch
 * the default connection to that tenant's database (R1.4, R3.4).
 */
class Domain extends BaseDomain
{
}
