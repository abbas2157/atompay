<?php

namespace App\Services;

use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\CreditHistory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Sections 3 and 5 of the KYC form, and the figures behind the dashboard.
 *
 *   approved limit  = limit_ratio      x monthly income
 *   max instalment  = min(instalment_ratio x income, disposable income)
 *   disposable      = income - existing instalments - monthly expenses
 *   in use          = unpaid AtomShop instalments (ObligationService)
 *
 * submit() records a customer's profile with a provisional risk score and
 * limit; decide() is where staff confirm, adjust or reject it.
 */
class CreditAssessmentService
{
    public function __construct(
        private readonly InstalmentQuoteService $quotes,
        private readonly RiskScoringService $risk,
        private readonly ObligationService $obligations,
    ) {}

    /* ------------------------------------------------------------ estimates */

    /** Pure estimate from income alone - the landing-page widget. */
    public function estimate(int $monthlyIncome): array
    {
        return [
            'monthly_income' => $monthlyIncome,
            'approved_limit' => Money::share($monthlyIncome, config('atompay.credit.limit_ratio')),
            'max_instalment' => Money::share($monthlyIncome, config('atompay.credit.instalment_ratio')),
        ];
    }

    /** Section 5 figures derived from a full Section 3 profile. */
    public function provisionalLimit(int $income, int $existingInstalments, int $expenses): array
    {
        $disposable = $income - $existingInstalments - $expenses;
        $base       = $this->estimate($income);

        return [
            'disposable_income' => $disposable,
            'approved_limit'    => $disposable > 0 ? $base['approved_limit'] : 0,
            'max_instalment'    => max(0, min($base['max_instalment'], $disposable)),
            'approved_tenure'   => max($this->quotes->tenures()),
        ];
    }

    /* -------------------------------------------------------------- writes */

    /**
     * Section 3 submitted by the customer. Always a new row: a pending
     * re-assessment must never overwrite the limit currently in force.
     *
     * @param array<string, mixed> $profile validated financial-profile fields
     */
    public function submit(User $user, array $profile): CreditAssessment
    {
        return DB::transaction(function () use ($user, $profile) {
            $limit = $this->provisionalLimit(
                (int) $profile['monthly_income'],
                (int) ($profile['existing_instalments'] ?? 0),
                (int) ($profile['monthly_expenses'] ?? 0),
            );

            $assessment = new CreditAssessment([
                ...$profile, ...$limit,
                'user_id' => $user->id,
                'status'  => AssessmentStatus::Pending,
            ]);

            // Provisional Section 4; staff's last credit-history view carries over.
            $risk = $this->risk->assess($user, $assessment, $user->creditAssessment?->credit_history);
            $assessment->fill($risk->toAttributes())->save();

            return $assessment;
        });
    }

    /**
     * Sections 4-5 confirmed by staff. Re-scores with their credit-history
     * input, then applies whatever limit / tenure / status they set.
     *
     * @param array<string, mixed> $decision credit_history, approved_limit, max_instalment, approved_tenure, status, notes
     */
    public function decide(CreditAssessment $assessment, User $staff, array $decision): CreditAssessment
    {
        $history = isset($decision['credit_history']) ? CreditHistory::from($decision['credit_history']) : null;
        $risk    = $this->risk->assess($assessment->user, $assessment, $history);

        $assessment->fill([
            ...$risk->toAttributes(),
            'credit_history'  => $history,
            'approved_limit'  => $decision['approved_limit'],
            'max_instalment'  => $decision['max_instalment'],
            'approved_tenure' => $decision['approved_tenure'] ?? $assessment->approved_tenure,
            'status'          => $decision['status'],
            'notes'           => $decision['notes'] ?? null,
            'decided_by'      => $staff->id,
            'decided_at'      => now(),
        ])->save();

        return $assessment;
    }

    /* --------------------------------------------------------------- reads */

    /** Figures for the limit card, whether or not a decision exists yet. */
    public function summary(User $user): array
    {
        $active = $user->activeAssessment;
        $limit  = $active?->approved_limit ?? 0;
        $used   = $this->obligations->exposure($user);

        return [
            'active'         => $active,
            'latest'         => $user->creditAssessment,
            'limit'          => $limit,
            'used'           => $used,
            'available'      => max(0, $limit - $used),
            'used_percent'   => $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0,
            'max_instalment' => $active?->max_instalment ?? 0,
            'tenure'         => $active?->approved_tenure,
        ];
    }
}
