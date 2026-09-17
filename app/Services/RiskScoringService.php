<?php

namespace App\Services;

use App\DataTransferObjects\RiskProfile;
use App\Models\CreditAssessment;
use App\Models\Enums\CreditHistory;
use App\Models\Enums\PaymentHistory;
use App\Models\Enums\RiskCategory;
use App\Models\OrderInstalment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Section 4 - Risk Assessment. Produces a provisional 0-100 score from
 * what we can observe (AtomShop payment record, obligations, disposable
 * income, KYC state) plus staff's credit-history view. Every penalty is
 * in config('atompay.risk') so the policy can be tuned without code.
 */
class RiskScoringService
{
    public function __construct(private readonly ObligationService $obligations) {}

    public function assess(User $user, CreditAssessment $assessment, ?CreditHistory $creditHistory = null): RiskProfile
    {
        $p           = config('atompay.risk.penalties');
        $history     = $this->paymentHistory($user);
        $obligations = $this->obligations->exposure($user);
        $lateCount   = $this->lateInstalmentCount($user);
        $reasons     = [];
        $score       = 100;

        // AtomShop repayment record
        if ($history === PaymentHistory::Defaulted) {
            $score -= $p['defaulted'];
            $reasons[] = 'Instalment overdue beyond '.config('atompay.risk.default_days').' days';
        }
        if ($lateCount > 0) {
            $penalty = min($lateCount * $p['late_instalment'], $p['late_instalment_cap']);
            $score  -= $penalty;
            $reasons[] = "{$lateCount} late instalment(s)";
        }
        if ($history === PaymentHistory::None) {
            $score -= $p['no_payment_history'];
            $reasons[] = 'No previous AtomShop plan';
        }

        // Monthly commitments (other lenders + AtomShop due next month) vs income
        $monthly = $assessment->existing_instalments + $this->obligations->monthlyCommitment($user);
        $ratio   = $assessment->monthly_income > 0 ? min(1, $monthly / $assessment->monthly_income) : 1;
        if ($ratio > 0) {
            $score -= (int) round($p['obligations_ratio'] * $ratio);
            $reasons[] = 'Monthly obligations are '.round($ratio * 100).'% of income';
        }

        // Affordability: disposable income below the income-based instalment cap
        $cap = Money::share($assessment->monthly_income, config('atompay.credit.instalment_ratio'));
        if ($assessment->disposable_income < $cap) {
            $score -= $p['low_disposable'];
            $reasons[] = 'Disposable income below the '.(int) (config('atompay.credit.instalment_ratio') * 100).'% instalment cap';
        }

        // Identity / address not yet confirmed
        if (! ($user->kycProfile?->isVerified() ?? false)) {
            $score -= $p['kyc_not_verified'];
            $reasons[] = 'KYC not yet verified';
        }

        // Staff's wider credit-history view
        if ($creditHistory) {
            $score -= $p['credit_history'][$creditHistory->value];
            $reasons[] = 'Credit history: '.$creditHistory->label();
        }

        $score = max(0, min(100, $score));

        return new RiskProfile(
            score: $score,
            category: RiskCategory::fromScore($score),
            paymentHistory: $history,
            existingObligations: $obligations,
            reasons: $reasons,
        );
    }

    public function paymentHistory(User $user): PaymentHistory
    {
        $rows = OrderInstalment::query()
            ->where('user_id', $user->id)->normalOrders()->monthly()
            ->get(['status', 'installment_date', 'installment_paid_date']);

        if ($rows->isEmpty()) {
            return PaymentHistory::None;
        }

        $defaultBefore = Carbon::today()->subDays(config('atompay.risk.default_days'));
        if ($rows->contains(fn ($r) => ! $r->isPaid() && $r->installment_date?->lt($defaultBefore))) {
            return PaymentHistory::Defaulted;
        }

        return $rows->contains(fn ($r) => $this->wasLate($r)) ? PaymentHistory::SomeLate : PaymentHistory::OnTime;
    }

    private function lateInstalmentCount(User $user): int
    {
        return OrderInstalment::query()
            ->where('user_id', $user->id)->normalOrders()->monthly()
            ->get(['status', 'installment_date', 'installment_paid_date'])
            ->filter(fn ($r) => $this->wasLate($r))
            ->count();
    }

    /** Paid after its due date, or unpaid and past due. */
    private function wasLate(OrderInstalment $row): bool
    {
        if (! $row->installment_date) {
            return false;
        }

        return $row->isPaid()
            ? ($row->installment_paid_date?->gt($row->installment_date) ?? false)
            : $row->isOverdue();
    }
}
