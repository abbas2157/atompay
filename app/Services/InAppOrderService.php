<?php

namespace App\Services;

use App\Exceptions\Api\BusinessRuleException;
use App\Models\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * "Buy with AtomPay" in the app (docs/api/orders.md): one eligible product,
 * on AtomPay's plan, delivered to the customer's verified address.
 *
 * AtomPay never writes Atomshop's tables (BelongsToAtomShop), so the order
 * itself is placed by Atomshop, server to server (POST api/atompay/orders
 * with the shared ATOMPAY_SERVER_KEY), through the same code as its website
 * checkout. What AtomPay adds is the check Atomshop leaves to staff: the
 * whole price within what the customer can still spend, and the largest
 * month within their monthly cap, so the app never sends an order that
 * would be refused at approval.
 */
class InAppOrderService
{
    public function __construct(
        private readonly CatalogueService $catalogue,
        private readonly CreditAssessmentService $credit,
        private readonly InstalmentQuoteService $quotes,
    ) {}

    /** On when the flag is set and the shared key is configured. */
    public static function enabled(): bool
    {
        return (bool) config('atompay.orders.enabled') && filled(config('atompay.orders.server_key'));
    }

    /**
     * The product the customer may buy now, or a 409 saying why not.
     *
     * @return array{product: Product, plan: array<string, mixed>}
     */
    public function check(User $user, int $productId): array
    {
        if (! self::enabled()) {
            throw new BusinessRuleException('orders_unavailable', 'Ordering in the app isn’t available yet. You can buy on Atomshop.pk and choose AtomPay at checkout.');
        }

        $product = $this->catalogue->find($productId) ?? abort(404, 'This product is not available with AtomPay.');

        $summary = $this->credit->summary($user->loadMissing(['creditAssessment', 'activeAssessment', 'kycProfile']));
        if (! $summary['active']) {
            throw new BusinessRuleException('limit_required', 'You need an approved AtomPay limit to order. Apply for your limit first.');
        }

        $price = (int) $product->price;
        $plan = $this->quotes->atompayPlan($price);
        $available = (int) $summary['available'];
        if ($price > $available) {
            throw new BusinessRuleException('over_limit', 'This costs Rs. '.number_format($price).', more than the Rs. '.number_format($available).' you have available.');
        }

        // Orders still awaiting approval aren't in `available` yet (it matches
        // Atomshop admin's exposure, which counts them from Processing), but
        // staff will count them: don't let a customer order past their limit.
        $awaiting = $this->awaitingApproval($user);
        if ($awaiting > 0 && $price + $awaiting > $available) {
            throw new BusinessRuleException('over_limit', 'You already have Rs. '.number_format($awaiting).' in orders awaiting approval, so Rs. '
                .number_format(max(0, $available - $awaiting)).' of your limit is left for new orders.');
        }

        $largest = max(array_column($plan['schedule'], 'amount'));
        $cap = (int) $summary['max_instalment'];
        if ($cap > 0 && $largest > $cap) {
            throw new BusinessRuleException('over_cap', 'Rs. '.number_format($largest).' a month is more than your Rs. '.number_format($cap).' monthly limit.');
        }

        $profile = $user->kycProfile;
        if (! $profile || ! $profile->city_id || ! $profile->area_id || blank($profile->residential_address) || blank($profile->mobile)) {
            throw new BusinessRuleException('address_required', 'Add your city, area and address to your profile so Atomshop can deliver.');
        }

        return ['product' => $product, 'plan' => $plan];
    }

    /** What the customer's AtomPay orders awaiting approval would finance. */
    private function awaitingApproval(User $user): int
    {
        return (int) Order::query()
            ->where('user_id', $user->id)
            ->where('type', Order::TYPE_ATOMPAY)
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Verification])
            ->get(['total_deal_price', 'advance_price'])
            ->sum(fn (Order $order) => $order->financed_amount);
    }

    /**
     * Places the order through Atomshop. One at a time per customer, so a
     * double tap never orders twice.
     *
     * @return array<string, mixed> Atomshop's summary of the order
     */
    public function place(User $user, int $productId, int $kfsAmount, ?string $ip, ?string $userAgent): array
    {
        ['product' => $product] = $this->check($user, $productId);

        $lock = Cache::lock('atompay:in-app-order:'.$user->id, 60);
        if (! $lock->get()) {
            throw new BusinessRuleException('order_in_progress', 'Your order is already being placed. Check My orders in a moment.');
        }

        try {
            $profile = $user->kycProfile;
            $response = Http::acceptJson()
                ->withHeaders(['X-AtomPay-Key' => (string) config('atompay.orders.server_key')])
                ->timeout((int) config('atompay.orders.timeout'))
                ->post(rtrim((string) config('atompay.orders.atomshop_api'), '/').'/atompay/orders', [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'full_name' => $profile->full_name ?: $user->name,
                    'phone' => $profile->mobile,
                    'city_id' => $profile->city_id,
                    'area_id' => $profile->area_id,
                    'address' => $profile->residential_address,
                    'kfs_amount' => $kfsAmount,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Atomshop did not answer an in-app order', ['user_id' => $user->id, 'product_id' => $product->id, 'error' => $e->getMessage()]);
            throw new RuntimeException('Atomshop did not answer.', previous: $e);
        } finally {
            $lock->release();
        }

        // Atomshop's 422 is written for the customer (a price change, the product gone).
        if ($response->status() === 422) {
            throw ValidationException::withMessages(['order' => [(string) ($response->json('message') ?: 'This order can’t be placed.')]]);
        }
        if (! $response->successful() || ! $response->json('success')) {
            Log::error('Atomshop refused an in-app order', ['user_id' => $user->id, 'product_id' => $product->id, 'status' => $response->status()]);
            throw new RuntimeException('Atomshop refused the order ('.$response->status().').');
        }

        return (array) $response->json('data');
    }
}
