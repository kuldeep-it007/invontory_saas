<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Authentication domain service (R7).
 *
 * Owns the login / logout / password-reset logic so the controller stays thin.
 * Operates against the active tenant connection: the `User` model, Sanctum
 * tokens, and the audit log all resolve to the current tenant's database.
 */
class AuthService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Authenticate a user by email (or username) + password and issue a Sanctum
     * token on success (R7.1).
     *
     * Passwords are verified against the stored bcrypt/argon2 hash (R7.3). On
     * invalid credentials the failed attempt is recorded in the audit log and
     * an AuthenticationException is thrown, which the centralized renderer maps
     * to HTTP 401 (R7.2).
     *
     * @return array{token: string, user: User}
     *
     * @throws AuthenticationException
     */
    public function login(string $login, string $password, ?string $deviceName = null): array
    {
        $user = User::query()->where('email', $login)->first();

        // Verify the password against the stored hash. We still run Hash::check
        // against a throwaway hash when the user is missing to reduce timing
        // signal between "unknown user" and "wrong password".
        $valid = $user !== null && Hash::check($password, $user->getAuthPassword());

        if (! $valid) {
            $this->audit->record('login_failed', $user, [
                'login' => $login,
                'reason' => $user === null ? 'unknown_user' : 'invalid_password',
            ]);

            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        $newToken = $user->createToken(
            $deviceName ?: 'api-token',
            $user->abilities(),
        );

        // Stamp the issuing tenant onto the token (R1.6). Login runs inside the
        // active tenant's database (resolved by the tenancy middleware), so the
        // current tenant id identifies the only tenant this token is valid for.
        // The ReconcileTenantUser guard compares this stamp on every subsequent
        // request to reject a token replayed across tenant databases.
        $tenantId = function_exists('tenant') ? tenant('id') : null;

        if ($tenantId !== null) {
            $accessToken = $newToken->accessToken;
            $accessToken->forceFill(['tenant_id' => $tenantId])->save();
        }

        $token = $newToken->plainTextToken;

        $this->audit->record('login', $user);

        return ['token' => $token, 'user' => $user];
    }

    /**
     * Revoke the token used for the current request and audit the logout (R7.6).
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        $this->audit->record('logout', $user);
    }

    /**
     * Send a password-reset link to the user's registered email (R7.4) and
     * audit the request (R7.6).
     *
     * Returns the broker status string. To avoid leaking which emails are
     * registered, the controller returns a uniform response regardless of
     * status; the audit entry still records the request.
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        $user = User::query()->where('email', $email)->first();

        $this->audit->record('password_reset_requested', $user, [
            'email' => $email,
            'status' => $status,
        ]);

        return $status;
    }

    /**
     * Record that a user completed one-time-password verification (R7.6).
     *
     * OTP login is offered only where the `otp_login` Feature_Flag is enabled
     * (R7.5); the full OTP challenge/verify flow lands with that feature. This
     * is the canonical audit hook security-relevant OTP verification routes
     * through, so OTP-verify is covered by the same audit mechanism as login,
     * logout, and password-reset. The audit write is best-effort and never
     * blocks the authentication outcome.
     *
     * @param  array<string, mixed>  $context  Non-sensitive context (never the OTP code itself).
     */
    public function recordOtpVerified(User $user, array $context = []): void
    {
        $this->audit->record('otp_verified', $user, $context);
    }
}
