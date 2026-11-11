<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Roles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates (or updates) a Platform_Admin superadmin in the CENTRAL database and
 * issues a Sanctum API token for it.
 *
 * The Platform_Admin lives centrally and operates on the `platform.admin` route
 * group (provisioning, plan management, impersonation) which runs WITHOUT
 * tenancy initialization against the central connection (R1.6). This command
 * bootstraps that otherwise-unseeded account so the central admin API is
 * usable, and prints a ready-to-use bearer token.
 *
 * Everything runs on the `central` connection explicitly, because the default
 * connection is also `central` only until a tenant is initialized; forcing it
 * here keeps the command correct regardless of context.
 *
 * Usage:
 *   php artisan platform:admin                       # defaults below
 *   php artisan platform:admin --email=x --password=y --name="Super Admin"
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
        {--email=superadmin@platform.test : Superadmin email}
        {--password=password123 : Superadmin password (min 8 chars)}
        {--name=Super Admin : Display name}
        {--token-name=platform-admin : Name for the issued Sanctum token}';

    protected $description = 'Create/update a central Platform_Admin superadmin and issue a Sanctum token';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');
        $tokenName = (string) $this->option('token-name');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // All central: pin the connection so this works pre- and post-tenancy.
        $guard = config('auth.defaults.guard', 'web');

        // 1) Ensure the Platform_Admin role + its abilities exist CENTRALLY.
        //    (The RoleSeeder normally runs per-tenant; the central DB needs the
        //    role too so RoleMiddleware can authorize the admin routes.)
        $this->ensureCentralRole($guard);

        // 2) Create or update the superadmin user on the central connection.
        /** @var User $user */
        $user = User::on('central')->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password; // hashed by the model's `hashed` cast (R7.3)
        $user->setConnection('central');
        $user->save();

        // 3) Assign the Platform_Admin role (idempotent).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (! $user->hasRole(Roles::PLATFORM_ADMIN)) {
            $user->assignRole(Roles::PLATFORM_ADMIN);
        }

        // 4) Issue a fresh Sanctum token. Revoke prior tokens of the same name
        //    so re-running does not pile up stale tokens.
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->newLine();
        $this->info('Platform_Admin superadmin ready (central database).');
        $this->line('  Email:    '.$email);
        $this->line('  Password: '.$password);
        $this->line('  Roles:    '.implode(', ', $user->getRoleNames()->all()));
        $this->newLine();
        $this->line('Bearer token (use on /api/admin/* routes):');
        $this->line('  '.$token);
        $this->newLine();
        $this->comment('Example:');
        $this->comment('  curl -k -H "Authorization: Bearer '.$token.'" https://localhost:8000/api/admin/ping');

        return self::SUCCESS;
    }

    /**
     * Create the Platform_Admin role and its full ability set on the central
     * connection, idempotently. spatie reads/writes the guard's configured
     * tables on the current default connection, which is `central` here.
     */
    private function ensureCentralRole(string $guard): void
    {
        DB::setDefaultConnection('central');

        foreach (Roles::allAbilities() as $ability) {
            Permission::findOrCreate($ability, $guard);
        }

        $role = Role::findOrCreate(Roles::PLATFORM_ADMIN, $guard);
        $role->syncPermissions(Roles::allAbilities());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
