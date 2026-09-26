<?php

namespace App\Console\Commands;

use App\Models\CreditAssessment;
use App\Models\CustomerNotification;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\OrderStatus;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use App\Models\OrderInstalment;
use App\Services\NotificationService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The notification sweep, scheduled every 10 minutes (routes/console.php).
 *
 * It looks at state rather than listening for events, because limits and
 * address checks are decided in two places - AtomPay's staff screens and
 * AtomShop's admin panel - and only the database sees both. Every message
 * has a dedupe key, so running it again never repeats one.
 */
class SendCustomerNotifications extends Command
{
    protected $signature = 'atompay:notify {--now= : Pretend the current time is this (testing)}';

    protected $description = 'Announce limit decisions, KYC outcomes and instalment reminders to customers';

    private int $sent = 0;

    public function handle(NotificationService $notifications): int
    {
        $config = config('atompay.notifications');
        $now = $this->option('now') ? Carbon::parse($this->option('now')) : now();
        $local = $now->copy()->setTimezone($config['timezone']);
        $since = $now->copy()->subDays($config['lookback_days']);

        $this->received($notifications, $since);
        $this->decisions($notifications, $since);
        $this->verifications($notifications, $since);

        if ($local->hour >= $config['quiet_before'] && $local->hour < $config['quiet_after']) {
            $this->reminders($notifications, $local->copy()->startOfDay(), $config);
        }

        $this->info("Notifications created: {$this->sent}");

        return self::SUCCESS;
    }

    /** Confirmation that an application (web or app) arrived and what happens next. */
    private function received(NotificationService $notifications, Carbon $since): void
    {
        CreditAssessment::query()
            ->where('created_at', '>=', $since)
            ->where('status', AssessmentStatus::Pending)   // already decided? the decision says it all
            ->with('user')
            ->chunkById(200, function ($assessments) use ($notifications) {
                foreach ($assessments as $a) {
                    if (! $a->user?->isCustomer()) {
                        continue;
                    }

                    $this->record($notifications->notify($a->user, CustomerNotification::TYPE_APPLICATION_RECEIVED,
                        "We've received your application",
                        'Our team will verify your details and confirm your purchase limit. We will let you know as soon as it is decided.',
                        ['screen' => 'dashboard', 'assessment_id' => $a->id],
                        "assessment:{$a->id}:received"));
                }
            });
    }

    private function decisions(NotificationService $notifications, Carbon $since): void
    {
        CreditAssessment::query()
            ->where('status', '!=', AssessmentStatus::Pending)
            ->where('decided_at', '>=', $since)
            ->with('user')
            ->chunkById(200, function ($assessments) use ($notifications) {
                foreach ($assessments as $a) {
                    if (! $a->user?->isCustomer()) {
                        continue;
                    }

                    [$title, $body] = match ($a->status) {
                        AssessmentStatus::Approved => ['Your AtomPay limit is approved',
                            'You can now spend up to '.Money::format($a->approved_limit).' with AtomPay at AtomShop checkout.'],
                        AssessmentStatus::Conditional => ['Your limit is approved with conditions',
                            $a->notes ?: 'Your limit of '.Money::format($a->approved_limit).' is approved with conditions. Open the app for details.'],
                        default => ['Application not approved',
                            $a->notes ?: 'We could not approve a limit this time. You can re-apply if your circumstances change.'],
                    };

                    $this->record($notifications->notify($a->user, CustomerNotification::TYPE_LIMIT_DECIDED, $title, $body,
                        ['screen' => 'dashboard', 'assessment_id' => $a->id],
                        "assessment:{$a->id}:{$a->status->value}"));
                }
            });
    }

    private function verifications(NotificationService $notifications, Carbon $since): void
    {
        KycProfile::query()
            ->whereIn('verification_status', [VerificationStatus::Verified, VerificationStatus::Rejected])
            ->where('updated_at', '>=', $since)
            ->with('user')
            ->chunkById(200, function ($profiles) use ($notifications) {
                foreach ($profiles as $p) {
                    if (! $p->user?->isCustomer()) {
                        continue;
                    }

                    $verified = $p->verification_status === VerificationStatus::Verified;
                    $this->record($notifications->notify($p->user,
                        $verified ? CustomerNotification::TYPE_KYC_VERIFIED : CustomerNotification::TYPE_KYC_REJECTED,
                        $verified ? 'Your address is verified' : 'We could not verify your details',
                        $verified
                            ? 'Thanks for your time. We are now reviewing your financial profile.'
                            : ($p->verification_notes ?: 'Please review your profile and submit it again.'),
                        ['screen' => $verified ? 'dashboard' : 'profile'],
                        "kyc:{$p->id}:{$p->verification_status->value}:".($p->verified_at?->toDateString() ?? $p->updated_at->timestamp)));
                }
            });
    }

    /** @param array{remind_before_days: int[], remind_overdue_days: int[]} $config */
    private function reminders(NotificationService $notifications, Carbon $today, array $config): void
    {
        $dates = [];
        foreach ($config['remind_before_days'] as $days) {
            $dates[$today->copy()->addDays($days)->toDateString()] = ['due', $days];
        }
        foreach ($config['remind_overdue_days'] as $days) {
            $dates[$today->copy()->subDays($days)->toDateString()] = ['overdue', $days];
        }

        OrderInstalment::query()
            ->normalOrders()->monthly()->unpaid()
            ->whereIn('installment_date', array_keys($dates))
            ->whereHas('order', fn ($q) => $q->whereIn('status', OrderStatus::active()))
            ->with(['order.cart.product', 'order.user'])
            ->chunkById(200, function ($instalments) use ($notifications, $dates) {
                foreach ($instalments as $i) {
                    $user = $i->order?->user;
                    if (! $user?->isCustomer()) {
                        continue;
                    }

                    [$kind, $days] = $dates[$i->installment_date->toDateString()];
                    $amount = Money::format($i->installment_price);
                    $product = $i->order->cart?->product?->title ?? 'your AtomShop order';
                    $date = $i->installment_date->format('j M');

                    [$title, $body] = match (true) {
                        $kind === 'overdue' => ['Instalment overdue', "{$amount} for {$product} was due on {$date}. Please pay to keep your limit in good standing."],
                        $days === 0 => ['Instalment due today', "{$amount} for {$product} is due today."],
                        default => ["Instalment due in {$days} days", "{$amount} for {$product} is due on {$date}."],
                    };

                    $this->record($notifications->notify($user,
                        $kind === 'overdue' ? CustomerNotification::TYPE_INSTALMENT_OVERDUE : CustomerNotification::TYPE_INSTALMENT_DUE,
                        $title, $body,
                        ['screen' => 'plan', 'order_id' => $i->order_id, 'instalment_id' => $i->id],
                        "instalment:{$i->id}:{$kind}:{$days}"));
                }
            });
    }

    private function record(?CustomerNotification $notification): void
    {
        if ($notification) {
            $this->sent++;
        }
    }
}
