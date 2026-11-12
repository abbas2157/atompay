<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InAppOrderService;
use App\Services\KeyFactStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Buy with AtomPay" (docs/api/orders.md): the Key Fact Statement for a
 * product, then the order. Atomshop places it; see InAppOrderService.
 */
class OrderController extends Controller
{
    public function __construct(private readonly InAppOrderService $orders) {}

    /**
     * GET /products/{product}/kfs: what the customer reads and accepts
     * before ordering. Checked like the order itself, so a product that
     * can't be ordered answers 409 here, before the customer reads anything.
     */
    public function kfs(Request $request, int $product, KeyFactStatementService $kfs): JsonResponse
    {
        ['product' => $found] = $this->orders->check($request->user(), $product);

        return response()->json(['data' => $kfs->forProduct($found)]);
    }

    /** POST /orders: { product_id, kfs_amount, accept_kfs: true }. 201 with Atomshop's summary. */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'product_id' => ['required', 'integer'],
            // The amount_financed of the statement the customer accepted.
            'kfs_amount' => ['required', 'integer', 'min:1'],
            'accept_kfs' => ['accepted'],
        ], [
            'accept_kfs.accepted' => 'Please read the Key Fact Statement and agree to its terms.',
        ]);

        $order = $this->orders->place(
            $request->user(),
            (int) $input['product_id'],
            (int) $input['kfs_amount'],
            $request->ip(),
            $request->userAgent(),
        );

        $id = (int) $order['order_id'];
        // AtomPay's plan: equal months, the last taking the rounding.
        $months = max(1, (int) ($order['months'] ?? 0));
        $instalments = [...array_fill(0, $months - 1, (int) ($order['monthly'] ?? 0)), (int) ($order['last_monthly'] ?? 0)];

        return response()->json(['data' => [
            'order_id' => $id,
            'order_number' => 'AS-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'product' => $order['product'] ?? null,
            'total' => (int) ($order['total'] ?? 0),
            'advance' => (int) ($order['advance'] ?? 0),
            'months' => $months,
            'instalments' => $instalments,
            'area' => $order['area'] ?? null,
            'status' => 'pending',
        ]], 201);
    }
}
