<?php

namespace App\Services;

use App\Exceptions\Api\BusinessRuleException;
use App\Models\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * $retryingUnconfirmed: the same attempt again after Atomshop didn't answer. That order may already be
     * among those awaiting approval, so they aren't counted against it (counting it would refuse the
     * customer's own order as over their limit). Staff re-check the limit before approving either way.
     *
     * @return array{product: Product, plan: array<string, mixed>}
     */
    public function check(User $user, int $productId, bool $retryingUnconfirmed = false): array
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
        $awaiting = $retryingUnconfirmed ? 0 : $this->awaitingApproval($user);
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

    /** How many times one order is sent when Atomshop doesn't answer. */
    private const TRIES = 2;

    /**
     * Places the order through Atomshop. One at a time per customer, so a
     * double tap never orders twice.
     *
     * Every attempt carries a request id, and Atomshop returns the order it
     * already placed for an id it has seen. So when Atomshop saved the order
     * but its answer was lost, the retry here (or the customer's next tap)
     * gets that order back instead of placing a second one. The id is the
     * app's when it sends one, otherwise one remembered per customer and
     * product until an attempt gets a definite answer.
     * Atomshop docs/trd/2026-11-18-critical-ecosystem-fixes.md §3
     *
     * @return array<string, mixed> Atomshop's summary of the order
     */
    public function place(User $user, int $productId, int $kfsAmount, ?string $ip, ?string $userAgent, ?string $requestId = null): array
    {
        $memo = 'atompay:in-app-order-request:'.$user->id.':'.$productId;
        $requestId = filled($requestId) ? (string) $requestId : (Cache::get($memo) ?: (string) Str::uuid());
        // Set when this attempt got no answer: Atomshop may hold the order already.
        $unconfirmed = 'atompay:in-app-order-unconfirmed:'.$user->id.':'.sha1($requestId);

        ['product' => $product] = $this->check($user, $productId, Cache::has($unconfirmed));

        $timeout = max(1, (int) config('atompay.orders.timeout'));
        // Outlives both tries, so a second tap can't start while the first is still waiting.
        $lock = Cache::lock('atompay:in-app-order:'.$user->id, self::TRIES * $timeout + 30);
        if (! $lock->get()) {
            throw new BusinessRuleException('order_in_progress', 'Your order is already being placed. Check My orders in a moment.');
        }

        Cache::put($memo, $requestId, now()->addMinutes(30));

        try {
            $response = $this->send($user, $product, $kfsAmount, $ip, $userAgent, $requestId, $timeout);
        } catch (ConnectionException $e) {
            // Atomshop may have saved the order before the line dropped: the id stays remembered, so trying
            // again returns that order rather than placing another.
            Log::error('Atomshop did not answer an in-app order', ['user_id' => $user->id, 'product_id' => $product->id, 'error' => $e->getMessage()]);
            Cache::put($unconfirmed, true, now()->addMinutes(30));
            throw new BusinessRuleException('order_unconfirmed', 'We couldn’t confirm your order with Atomshop. Check My orders in a minute before you try again.');
        } finally {
            $lock->release();
        }

        // Atomshop's 422 is written for the customer (a price change, the product gone). Nothing was saved.
        if ($response->status() === 422) {
            Cache::deleteMultiple([$memo, $unconfirmed]);
            throw ValidationException::withMessages(['order' => [(string) ($response->json('message') ?: 'This order can’t be placed.')]]);
        }
        if (! $response->successful() || ! $response->json('success')) {
            Log::error('Atomshop refused an in-app order', ['user_id' => $user->id, 'product_id' => $product->id, 'status' => $response->status()]);
            throw new RuntimeException('Atomshop refused the order ('.$response->status().').');
        }

        Cache::deleteMultiple([$memo, $unconfirmed]);

        return (array) $response->json('data');
    }

    /**
     * POST api/atompay/orders, sent again once (same request id) when the
     * connection fails: a repeat is safe, Atomshop answers it with the order
     * it already has.
     *
     * @throws ConnectionException when neither try got an answer
     */
    private function send(User $user, Product $product, int $kfsAmount, ?string $ip, ?string $userAgent, string $requestId, int $timeout): Response
    {
        $profile = $user->kycProfile;
        $body = [
            'request_id' => $requestId,
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
        ];

        for ($try = 1; ; $try++) {
            try {
                return Http::acceptJson()
                    ->withHeaders(['X-AtomPay-Key' => (string) config('atompay.orders.server_key')])
                    ->timeout($timeout)
                    ->post(rtrim((string) config('atompay.orders.atomshop_api'), '/').'/atompay/orders', $body);
            } catch (ConnectionException $e) {
                if ($try >= self::TRIES) {
                    throw $e;
                }
            }
        }
    }
}
