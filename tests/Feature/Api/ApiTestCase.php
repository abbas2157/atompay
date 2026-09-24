<?php

namespace Tests\Feature\Api;

use App\Models\CreditAssessment;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CreditAssessmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile API tests run on the shared AtomShop DB inside a transaction, with
 * accounts made on the spot so the password is known.
 */
abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected const PASSWORD = 'correct-horse-battery';

    protected function makeCustomer(array $overrides = []): User
    {
        return app(AccountService::class)->registerCustomer([
            'name' => 'Api Test Customer',
            'phone' => '0399'.random_int(1000000, 9999999),
            'email' => 'api-'.Str::lower(Str::random(10)).'@example.test',
            'password' => self::PASSWORD,
            ...$overrides,
        ]);
    }

    /** A real bearer token, issued the way the login endpoint issues one. */
    protected function tokenFor(User $user): string
    {
        return $user->createToken('test device', ['*'], now()->addDay())->plainTextToken;
    }

    /**
     * Sanctum's guard remembers the user it resolved for the whole test;
     * forget it so the next request re-reads the token from the database.
     */
    protected function freshRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    /** A submitted Section 1 profile, without going through uploads. */
    protected function makeProfile(User $user, VerificationStatus $status = VerificationStatus::Pending): KycProfile
    {
        return KycProfile::create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'cnic' => '42101'.random_int(10000000, 99999999),
            'mobile' => $user->phone,
            'date_of_birth' => '1990-01-01',
            'residential_address' => 'House 1, Karachi',
            'cnic_front_path' => 'kyc/test/front.jpg',
            'cnic_back_path' => 'kyc/test/back.jpg',
            'selfie_path' => 'kyc/test/selfie.jpg',
            'submitted_at' => now(),
            'verification_status' => $status,
        ]);
    }

    /** Section 3 submitted and decided by "staff" (any user id will do for the audit column). */
    protected function makeDecidedAssessment(User $user, string $status = 'approved', int $limit = 60000): CreditAssessment
    {
        $credit = app(CreditAssessmentService::class);
        $assessment = $credit->submit($user->fresh(['kycProfile', 'creditAssessment']), [
            'employment_status' => 'salaried', 'employer_name' => 'Acme', 'income_source' => 'salary',
            'monthly_income' => 200000, 'existing_instalments' => 0, 'monthly_expenses' => 50000,
        ]);

        return $credit->decide($assessment, $user, [
            'credit_history' => 'good', 'approved_limit' => $limit, 'max_instalment' => 20000,
            'approved_tenure' => 12, 'status' => $status, 'notes' => null,
        ]);
    }

    /**
     * An AtomShop instalment order, written straight into the shop's tables
     * (the transaction rolls it back).
     *
     * @param list<array{0: Carbon|string, 1: int, 2?: bool}> $instalments [due date, amount, paid?]
     */
    protected function makeOrder(User $user, array $instalments, string $status = 'Instalments'): int
    {
        $productId = DB::table('products')->value('id');
        $cartId = DB::table('carts')->insertGetId([
            'user_id' => $user->id, 'product_id' => $productId, 'product_price' => 120000,
            'status' => 'Purchased', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'cart_id' => $cartId,
            'total_deal_price' => 140000, 'advance_price' => 24000, 'instalment_tenure' => count($instalments),
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($instalments as $n => [$due, $amount, $paid]) {
            DB::table('order_instalments')->insert([
                'user_id' => $user->id, 'order_id' => $orderId, 'month' => 'Instalment '.($n + 1),
                'installment_price' => $amount, 'installment_date' => Carbon::parse($due)->toDateString(),
                'installment_paid_price' => $paid ? $amount : null,
                'installment_paid_date' => $paid ? Carbon::parse($due)->toDateString() : null,
                'type' => 'Instalment', 'status' => $paid ? 'Paid' : 'Unpaid', 'order_type' => 'Normal',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $orderId;
    }
}
