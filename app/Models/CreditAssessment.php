<?php

namespace App\Models;

use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\CreditHistory;
use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Models\Enums\PaymentHistory;
use App\Models\Enums\RiskCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AtomPay-owned. Sections 3-5 of the KYC form: the customer's financial
 * profile, the risk assessment and the purchase-limit decision. Numbers
 * are computed by CreditAssessmentService / RiskScoringService and frozen
 * here so a later config change does not silently move a decided limit.
 */
class CreditAssessment extends Model
{
    protected $table = 'atompay_credit_assessments';

    protected $fillable = [
        'user_id',
        'employment_status', 'employer_name', 'income_source', 'monthly_income',
        'existing_instalments', 'monthly_expenses', 'disposable_income',
        'credit_history', 'payment_history', 'existing_obligations', 'risk_score', 'risk_category',
        'approved_limit', 'max_instalment', 'approved_tenure', 'status', 'decided_by', 'decided_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'employment_status'    => EmploymentStatus::class,
            'income_source'        => IncomeSource::class,
            'monthly_income'       => 'integer',
            'existing_instalments' => 'integer',
            'monthly_expenses'     => 'integer',
            'disposable_income'    => 'integer',
            'credit_history'       => CreditHistory::class,
            'payment_history'      => PaymentHistory::class,
            'existing_obligations' => 'integer',
            'risk_score'           => 'integer',
            'risk_category'        => RiskCategory::class,
            'approved_limit'       => 'integer',
            'max_instalment'       => 'integer',
            'approved_tenure'      => 'integer',
            'status'               => AssessmentStatus::class,
            'decided_at'           => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------- scopes */

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', AssessmentStatus::Pending);
    }

    /** Decided in a way that lets the customer spend the limit. */
    public function scopeUsable(Builder $q): Builder
    {
        return $q->whereIn('status', AssessmentStatus::usable());
    }

    /* ----------------------------------------------------------- relations */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /* ------------------------------------------------------------ helpers */

    public function isPending(): bool
    {
        return $this->status === AssessmentStatus::Pending;
    }

    public function isUsable(): bool
    {
        return in_array($this->status, AssessmentStatus::usable(), true);
    }
}
