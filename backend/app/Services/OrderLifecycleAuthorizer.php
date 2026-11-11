<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Authorizes who may CHANGE THE STATUS of an order (R21.1, R21.2, R21.4).
 *
 * Business rule: an order's status may only be changed by the party that OWNS
 * THE STOCK the order draws from — i.e. the order's SELLER. Orders fulfilled
 * from Company stock may only be driven by the Company; orders fulfilled from a
 * Distributor's stock (a dealer buying from that distributor) may only be
 * driven by that same Distributor. This is enforced here, server-side, on top
 * of the `permission:orders.confirm` route gate, so the gate answers "may this
 * role touch order lifecycles at all" while this answers "is this actor the
 * seller of THIS order".
 *
 * The acting seller party is resolved from the authenticated tenant user:
 *   • a user linked to a Distributor (`distributor_id`) acts as that Distributor;
 *   • a user linked to a Dealer (`dealer_id`) is a buyer only — it owns no
 *     seller stock, so it may change no order's status;
 *   • any other (company-side) user acts as the Company (the tenant singleton).
 *
 * A mismatch throws a 403 {@see AccessDeniedHttpException}, mapped to the
 * standard error envelope by {@see \App\Exceptions\ApiExceptionRenderer}.
 */
class OrderLifecycleAuthorizer
{
    /**
     * Assert the given user may change the status of the given order, i.e. the
     * user is the order's seller (the owner of the stock it draws from).
     *
     * @throws AccessDeniedHttpException When the actor is not the order's seller.
     */
    public function assertCanManage(?User $user, Order $order): void
    {
        if ($user === null) {
            throw new AccessDeniedHttpException('Authentication is required to change an order status.');
        }

        [$type, $id] = $this->sellerIdentityFor($user);

        $matches = $type !== null
            && $id !== null
            && $order->seller_type === $type
            && (int) $order->seller_id === $id;

        if (! $matches) {
            throw new AccessDeniedHttpException(
                'Only the party that owns the stock for this order can change its status.',
            );
        }
    }

    /**
     * The (seller_type, seller_id) the user acts as, or [null, null] when the
     * user owns no seller stock (a dealer panel user).
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function sellerIdentityFor(User $user): array
    {
        // A dealer panel user is a buyer only — it never owns seller stock.
        if ($user->dealer_id !== null) {
            return [null, null];
        }

        // A distributor panel user acts as its Distributor party.
        if ($user->distributor_id !== null) {
            return [(new Distributor())->getMorphClass(), (int) $user->distributor_id];
        }

        // Otherwise a company-side user acts as the Company (tenant singleton).
        $company = Company::query()->first();
        if ($company === null) {
            return [null, null];
        }

        return [$company->getMorphClass(), (int) $company->getKey()];
    }
}
