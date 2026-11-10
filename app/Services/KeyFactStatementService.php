<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\User;

/**
 * The Key Fact Statement for one AtomPay order (SECP Circular 15/2022):
 * amount, fees, APR, tenor, and every instalment with its date.
 *
 * Figures come from the order Atomshop wrote. Before Atomshop raises the
 * schedule (an order awaiting approval) the instalments are AtomPay's plan
 * for the order's price, with no dates yet - `schedule_final` says which.
 *
 * Anything the business has not supplied (lender identity, late-payment and
 * early-settlement terms) is null, never invented.
 */
class KeyFactStatementService
{
    public function __construct(
        private readonly PaymentScheduleService $schedule,
        private readonly InstalmentQuoteService $quotes,
    ) {}

    /** Null when the order is not the customer's, or is not an AtomPay order. */
    public function forOrder(User $user, int $orderId): ?array
    {
        $plan = $this->schedule->forOrder($user, $orderId);

        /** @var Order|null $order */
        $order = $plan['order'] ?? null;

        if (! $order || $order->type !== Order::TYPE_ATOMPAY) {
            return null;
        }

        $financed = (int) $order->financed_amount;
        $instalments = $plan['instalments'];
        $final = $instalments->isNotEmpty();

        $rows = $final
            ? $instalments->values()->map(fn (OrderInstalment $i, int $n) => [
                'number' => $n + 1,
                'due_date' => $i->installment_date?->toDateString(),
                'amount' => (int) $i->installment_price,
            ])->all()
            : array_map(fn (array $s) => [
                'number' => $s['number'],
                'due_date' => null,            // set when Atomshop delivers and raises the schedule
                'amount' => $s['amount'],
            ], $this->quotes->atompayPlan($financed)['schedule']);

        $feePercent = (float) config('atompay.atompay_plan.processing_fee_percent');
        $fee = (int) round($financed * $feePercent / 100);
        $amounts = array_column($rows, 'amount');

        return [
            'order_id' => $order->id,
            'order_number' => $order->reference,
            'product' => $plan['product']?->title,
            'lender' => [
                'name' => config('atompay.lender.name') ?: null,
                'licence' => config('atompay.lender.licence') ?: null,
                'address' => config('atompay.lender.address') ?: null,
            ],
            'amount_financed' => $financed,
            'advance' => (int) $order->advance_price,
            'markup_percent' => (float) config('atompay.atompay_plan.markup_percent'),
            'processing_fee_percent' => $feePercent,
            'processing_fee' => $fee,
            'apr_percent' => self::apr($financed, $fee, $amounts),
            'months' => count($rows),
            'schedule_final' => $final,
            'instalments' => $rows,
            'total_repayable' => array_sum($amounts),
            'late_payment' => config('atompay.kfs.late_payment') ?: null,
            'early_settlement' => config('atompay.kfs.early_settlement') ?: null,
            'complaints' => config('atompay.kfs.complaints'),
            'issued_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Nominal annual rate, in percent to one decimal, that makes the monthly
     * payments worth what the customer actually received: the amount financed
     * less the fee paid for it. 0 when there is no fee and no markup.
     *
     * @param  list<int>  $payments  one per month, in order
     */
    public static function apr(int $financed, int $fee, array $payments): float
    {
        $net = $financed - $fee;

        if ($net <= 0 || $payments === [] || array_sum($payments) <= $net) {
            return 0.0;
        }

        $value = function (float $rate) use ($payments): float {
            $total = 0.0;
            foreach ($payments as $i => $amount) {
                $total += $amount / (1 + $rate) ** ($i + 1);
            }

            return $total;
        };

        // Present value falls as the rate rises: bisect for value == net.
        [$low, $high] = [0.0, 1.0];
        for ($i = 0; $i < 100; $i++) {
            $mid = ($low + $high) / 2;
            $value($mid) > $net ? $low = $mid : $high = $mid;
        }

        return round($high * 12 * 100, 1);
    }
}
