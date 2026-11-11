<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\CreditAssessment;
use App\Models\CustomerNotification;
use App\Models\CustomOrder;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use App\Models\Order;
use App\Models\PasswordReset;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\AccountService;
use App\Services\CreditAssessmentService;
use App\Services\InstalmentQuoteService;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * (Re)creates the two sign-ins Apple and Google reviewers use - see
 * config('atompay.review_accounts'). Safe to run again after a review has
 * changed them: each account's AtomPay data and its own orders are wiped
 * and rebuilt, and the password is set back.
 *
 *   A  full:  KYC verified, an approved limit, an AtomPay order being repaid
 *             (one instalment paid, one due, one upcoming) and an inbox.
 *   B  empty: KYC verified, an approved limit and no orders, so "Delete
 *             account" succeeds (a 409 on the only demo account looks broken).
 *
 * The accounts are marked on their KYC profile. An existing account that does
 * not carry the mark is a real customer's and is never touched; a reviewer
 * who deleted an account leaves an anonymised row, and a fresh one is made.
 */
class SeedReviewAccounts extends Command
{
    use ConfirmableTrait;

    public const MARK = 'App store review account - made by atompay:seed-review-accounts. Not a real customer.';

    protected $signature = 'atompay:seed-review-accounts {--force : Run in production without asking}';

    protected $description = 'Create or reset the two App Store / Google Play reviewer accounts';

    public function handle(AccountService $accounts, CreditAssessmentService $credit, InstalmentQuoteService $quotes): int
    {
        $config = config('atompay.review_accounts');

        foreach (['a', 'b'] as $key) {
            if (blank($config[$key]['email'] ?? null) || blank($config[$key]['password'] ?? null)) {
                $upper = strtoupper($key);
                $this->error("  Set ATOMPAY_REVIEW_{$upper}_EMAIL and ATOMPAY_REVIEW_{$upper}_PASSWORD in .env first.");

                return self::FAILURE;
            }
        }

        if (strcasecmp($config['a']['email'], $config['b']['email']) === 0) {
            $this->error('  Review A and Review B need different emails.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed('This writes two demo customers (and one order) into the shared AtomShop database.')) {
            return self::FAILURE;
        }

        try {
            $a = $this->account($accounts, $config['a'], 'Review Account A');
            $b = $this->account($accounts, $config['b'], 'Review Account B');
        } catch (RuntimeException $e) {
            $this->error('  '.$e->getMessage());

            return self::FAILURE;
        }

        $staff = User::query()->whereIn('role', config('atompay.staff_roles'))->orderBy('id')->first();

        DB::transaction(function () use ($a, $b, $credit, $quotes, $staff) {
            foreach ([$a, $b] as $user) {
                $this->wipe($user);
                $this->verifiedProfile($user, $staff);
            }

            $orderTotal = $this->order($a, $quotes);
            $this->limit($a, $credit, $staff, max(100000, (int) ceil($orderTotal * 1.5 / 10000) * 10000));
            $this->inbox($a, withDue: true);

            $this->limit($b, $credit, $staff, 60000);
            $this->inbox($b, withDue: false);
        });

        $this->newLine();
        $this->info('  Review accounts ready:');
        $this->line("    A (full)   {$a->email}   user #{$a->id}");
        $this->line("    B (empty)  {$b->email}   user #{$b->id}");
        $this->line('  Passwords are the ones in .env. Send both to the app team privately for the store review notes.');
        $this->newLine();

        return self::SUCCESS;
    }

    /** The account on this email: the one made last time, or a new one. */
    private function account(AccountService $accounts, array $credentials, string $name): User
    {
        $email = mb_strtolower(trim($credentials['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = $accounts->registerCustomer(['name' => $name, 'phone' => null, 'email' => $email, 'password' => $credentials['password']]);
            $this->line("  created {$email}");
        } elseif (! $this->madeByUs($user, $name)) {
            throw new RuntimeException("{$email} already belongs to an account this command did not make (user #{$user->id}). Use another email.");
        } else {
            $this->line("  resetting {$email}");
        }

        $user->forceFill([
            'name' => $name,
            'password' => $credentials['password'],          // hashed by the model cast
            'remember_token' => Str::random(60),
            'role' => config('atompay.customer_role'),
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Marked on its KYC profile - or, if a run stopped before the profile was
     * written, still the bare customer this command registers: our name, no
     * profile and no orders. Anything else is someone's real account.
     */
    private function madeByUs(User $user, string $name): bool
    {
        if ($user->kycProfile) {
            return $user->kycProfile->verification_notes === self::MARK;
        }

        return $user->name === $name && $user->isCustomer() && ! $user->orders()->exists();
    }

    /** Everything a review may have changed: sign-ins, AtomPay data, and this account's own orders. */
    private function wipe(User $user): void
    {
        $user->tokens()->delete();
        $user->devices()->delete();
        CustomerNotification::where('user_id', $user->id)->delete();
        UserPreference::where('user_id', $user->id)->delete();
        PasswordReset::where('user_id', $user->id)->delete();
        Complaint::where('user_id', $user->id)->delete();
        CreditAssessment::where('user_id', $user->id)->delete();

        if ($profile = KycProfile::where('user_id', $user->id)->first()) {
            $disk = Storage::disk(config('atompay.kyc.disk'));
            foreach (array_filter([$profile->cnic_front_path, $profile->cnic_back_path, $profile->selfie_path]) as $path) {
                $disk->delete($path);
            }
            $profile->delete();
        }

        $orderIds = Order::where('user_id', $user->id)->pluck('id');
        $mirrorIds = CustomOrder::query()->whereIn('source_order_id', $orderIds)->pluck('id');

        DB::table('order_instalments')->where('user_id', $user->id)->delete();
        DB::table('order_change_histories')->where(fn ($q) => $q
            ->where(fn ($n) => $n->where('order_type', 'Normal')->whereIn('order_id', $orderIds))
            ->orWhere(fn ($c) => $c->where('order_type', 'Custom')->whereIn('order_id', $mirrorIds)))->delete();
        DB::table('custom_orders')->whereIn('id', $mirrorIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
        DB::table('carts')->where('user_id', $user->id)->delete();
    }

    private function verifiedProfile(User $user, ?User $staff): void
    {
        $dir = config('atompay.kyc.path').'/'.$user->id;
        $documents = [];
        foreach (['cnic_front' => 'CNIC FRONT', 'cnic_back' => 'CNIC BACK', 'selfie' => 'SELFIE'] as $key => $label) {
            $path = "{$dir}/review-{$key}.jpg";
            Storage::disk(config('atompay.kyc.disk'))->put($path, $this->sampleImage($label));
            $documents[KycProfile::DOCUMENTS[$key]] = $path;
        }

        KycProfile::create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'cnic' => '9000000'.str_pad((string) ($user->id % 1000000), 6, '0', STR_PAD_LEFT),   // starts with 9: no province uses it
            'mobile' => '03000000000',
            'date_of_birth' => '1992-05-14',
            'residential_address' => 'House 12, Street 4, Gulberg III, Lahore',
            'city_id' => DB::table('cities')->where('title', 'Lahore')->value('id'),
            ...$documents,
            'face_verified' => true,
            'submitted_at' => now()->subDays(45),
            'address_verified' => true,
            'verified_by' => $staff?->id,
            'verified_at' => now()->subDays(44),
            'verification_status' => VerificationStatus::Verified,
            'verification_notes' => self::MARK,
        ]);
    }

    private function limit(User $user, CreditAssessmentService $credit, ?User $staff, int $limit): void
    {
        $assessment = $credit->submit($user->fresh(['kycProfile', 'creditAssessment']), [
            'employment_status' => 'salaried', 'employer_name' => 'Sample Employer', 'income_source' => 'salary',
            'monthly_income' => 250000, 'existing_instalments' => 0, 'monthly_expenses' => 90000,
        ]);

        $credit->decide($assessment, $staff ?? $user, [
            'credit_history' => 'good', 'approved_limit' => $limit, 'max_instalment' => (int) round($limit / 3),
            'approved_tenure' => 3, 'status' => 'approved', 'notes' => self::MARK,
        ]);
    }

    /**
     * One AtomPay order the way AtomShop writes it: the orders row, plus a
     * custom_orders mirror that holds the schedule. Delivered a month ago;
     * 1st instalment paid by JazzCash, 2nd due in 5 days, 3rd next month.
     *
     * @return int the amount financed
     */
    private function order(User $user, InstalmentQuoteService $quotes): int
    {
        $eligible = Product::query()->visible()->atompayEligible()->where('price', '>', 0)->whereNotNull('picture');
        $product = (clone $eligible)->whereBetween('price', [30000, 90000])->orderByDesc('feature')->orderBy('price')->first()
            ?? $eligible->orderBy('price')->first()
            ?? throw new RuntimeException('No AtomPay-eligible product with a picture to put on the demo order.');

        $plan = $quotes->atompayPlan((int) $product->price);
        $placed = now()->subDays(40)->setTime(11, 15);
        $delivered = (clone $placed)->addDays(3)->setTime(15, 40);
        // AtomShop's columns hold Karachi wall-clock time (see App\Support\ShopTime).
        $shop = fn (Carbon $at) => $at->clone()->setTimezone(config('atompay.shop_timezone'))->format('Y-m-d H:i:s');

        $cartId = DB::table('carts')->insertGetId([
            'user_id' => $user->id, 'product_id' => $product->id, 'product_price' => $plan['price'],
            'status' => 'Purchased', 'created_at' => $shop($placed), 'updated_at' => $shop($placed),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'cart_id' => $cartId, 'type' => Order::TYPE_ATOMPAY,
            'total_deal_price' => $plan['total'], 'advance_price' => $plan['advance'], 'instalment_tenure' => $plan['months'],
            'status' => 'Instalments', 'created_at' => $shop($placed), 'updated_at' => $shop($delivered),
        ]);
        $mirrorId = DB::table('custom_orders')->insertGetId([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'product_id' => 0, 'source_order_id' => $orderId,
            'total_deal_price' => $plan['total'], 'advance_price' => $plan['advance'], 'tenure' => $plan['months'],
            'type' => CustomOrder::TYPE_ATOMPAY, 'status' => 'Instalments',
            'created_at' => $shop($placed), 'updated_at' => $shop($delivered),
        ]);

        foreach (['Processing' => $placed->clone()->addDay(), 'Delivered' => $delivered, 'Instalments' => $delivered->clone()->addMinutes(5)] as $status => $at) {
            DB::table('order_change_histories')->insert([
                'user_id' => $user->id, 'order_id' => $mirrorId, 'order_type' => 'Custom', 'status' => $status,
                'role' => 'admin', 'created_at' => $shop($at), 'updated_at' => $shop($at),
            ]);
        }

        $dues = [now()->subDays(25), now()->addDays(5), now()->addDays(35)];
        $labels = ['1st Instalment', '2nd Instalment', '3rd Instalment', '4th Instalment', '5th Instalment', '6th Instalment'];
        foreach ($plan['schedule'] as $n => $row) {
            $due = $dues[$n] ?? now()->addDays(35 + 30 * ($n - 2));
            $paid = $n === 0;
            DB::table('order_instalments')->insert([
                'user_id' => $user->id, 'order_id' => $mirrorId, 'month' => $labels[$n] ?? 'Instalment '.($n + 1),
                'installment_price' => $row['amount'], 'installment_date' => $due->toDateString(),
                'installment_paid_price' => $paid ? $row['amount'] : null,
                'installment_paid_date' => $paid ? $due->clone()->subDay()->toDateString() : null,
                'payment_method' => $paid ? 'JazzCash' : null,
                'type' => 'Instalment', 'status' => $paid ? 'Paid' : 'Unpaid', 'order_type' => 'Custom',
                'created_at' => $shop($delivered), 'updated_at' => $shop($paid ? $due->clone()->subDay() : $delivered),
            ]);
        }

        return $plan['financed'];
    }

    private function inbox(User $user, bool $withDue): void
    {
        $messages = [
            [CustomerNotification::TYPE_KYC_VERIFIED, 'Your identity is verified', 'Thanks - your CNIC and selfie have been checked.', ['screen' => 'profile'], 44, true],
            [CustomerNotification::TYPE_LIMIT_DECIDED, 'Your purchase limit is approved', 'You can now shop on Atomshop.pk with AtomPay.', ['screen' => 'dashboard'], 43, true],
        ];
        if ($withDue) {
            $orderId = Order::where('user_id', $user->id)->value('id');
            $messages[] = [CustomerNotification::TYPE_INSTALMENT_DUE, 'An instalment is due soon', 'Your 2nd instalment is due in 5 days.', ['screen' => 'plan', 'order_id' => (string) $orderId], 0, false];
        }

        foreach ($messages as [$type, $title, $body, $data, $daysAgo, $read]) {
            $at = now()->subDays($daysAgo);
            $notification = CustomerNotification::create([
                'user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => $data,
                'dedupe_key' => "review:{$user->id}:{$type}:".Str::random(12),
                'read_at' => $read ? $at->clone()->addHour() : null,
            ]);
            $notification->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }

    /** A plainly fake document picture, so the KYC screens have something to open. */
    private function sampleImage(string $label): string
    {
        $image = imagecreatetruecolor(640, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 226, 232, 240));
        $ink = imagecolorallocate($image, 51, 65, 85);
        imagerectangle($image, 12, 12, 627, 387, $ink);
        imagestring($image, 5, 40, 170, "SAMPLE {$label}", $ink);
        imagestring($image, 3, 40, 200, 'App store review account - not a real document', $ink);

        ob_start();
        imagejpeg($image, null, 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
