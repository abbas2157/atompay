<?php

namespace Tests\Feature\Api;

use App\Mail\AccountDeletedMail;
use App\Models\CreditAssessment;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AccountDeletionApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake(config('atompay.kyc.disk'));
    }

    public function test_a_customer_deletes_their_account_and_its_personal_data(): void
    {
        $user = $this->makeCustomer(['phone' => '03998880001']);
        $email = $user->email;
        $token = $this->tokenFor($user);
        $this->tokenFor($user);   // another phone
        $user->devices()->create(['fcm_token' => 'fcm-1', 'fcm_token_hash' => hash('sha256', 'fcm-1'), 'platform' => 'android']);
        $profile = $this->makeProfile($user);
        $disk = Storage::disk(config('atompay.kyc.disk'));
        foreach (KycProfile::DOCUMENTS as $column) {
            $disk->put($profile->{$column}, 'image');
        }
        $this->makeDecidedAssessment($user);   // never became an order
        $user->appNotifications()->create(['type' => 'limit_decided', 'title' => 'T', 'body' => 'B', 'dedupe_key' => 'del-test-'.$user->id]);
        DB::table('customers')->insert(['user_id' => $user->id, 'cnic_no' => '4210112345671', 'address' => 'House 1', 'created_at' => now()]);

        $this->withToken($token)->postJson('/api/v1/me/delete', ['password' => self::PASSWORD])->assertNoContent();

        // Signed out everywhere.
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->devices()->count());
        $this->freshRequest()->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();

        // AtomPay data and documents gone.
        $this->assertNull(KycProfile::where('user_id', $user->id)->first());
        foreach (KycProfile::DOCUMENTS as $column) {
            $disk->assertMissing($profile->{$column});
        }
        $this->assertSame(0, CreditAssessment::where('user_id', $user->id)->count());
        $this->assertSame(0, $user->appNotifications()->count());

        // The AtomShop account is blocked and names nobody.
        $row = User::find($user->id);
        $this->assertSame('Deleted user', $row->name);
        $this->assertSame("deleted+{$user->id}@deleted.invalid", $row->email);
        $this->assertSame('block', $row->status);
        $this->assertFalse($row->hasRealEmail());
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $this->assertNull($customer->cnic_no);
        $this->assertNull($customer->address);

        // The old password no longer signs in; the email and number can register again.
        $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => self::PASSWORD])->assertUnprocessable();
        $this->assertNotNull($this->makeCustomer(['email' => $email, 'phone' => '03998880001'])->id);

        // Audit row without PII, and the goodbye to the address the account had.
        $audit = DB::table('atompay_account_deletions')->where('user_id', $user->id)->first();
        $this->assertSame('app', $audit->source);
        $this->assertSame('self-service deletion', $audit->reason);
        Mail::assertQueued(AccountDeletedMail::class, fn (AccountDeletedMail $m) => $m->hasTo($email));
    }

    public function test_the_wrong_password_deletes_nothing(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/me/delete', ['password' => 'not-it'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', "That password isn't right.");
        $this->withToken($token)->postJson('/api/v1/me/delete', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertSame('active', $user->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_deletion_waits_until_every_plan_is_repaid(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);
        $this->makeOrder($user, [['2026-09-01', 10000, true], ['2026-11-01', 10000, false]]);

        $this->withToken($token)->postJson('/api/v1/me/delete', ['password' => self::PASSWORD])
            ->assertStatus(409)
            ->assertJsonPath('code', 'outstanding_balance')
            ->assertJsonPath('message', 'You still have instalments to pay. You can delete your account once every plan is repaid.');

        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_an_order_awaiting_approval_also_blocks_deletion(): void
    {
        $user = $this->makeCustomer();
        $this->makeOrder($user, [], 'Pending');

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/me/delete', ['password' => self::PASSWORD])
            ->assertStatus(409)
            ->assertJsonPath('code', 'outstanding_balance');
    }

    public function test_a_repaid_customer_can_delete_and_the_financial_records_stay(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $assessment = $this->makeDecidedAssessment($user);
        $orderId = $this->makeOrder($user, [['2026-08-01', 10000, true], ['2026-09-01', 10000, true]], 'Completed');

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/me/delete', ['password' => self::PASSWORD])->assertNoContent();

        $this->assertSame($user->id, DB::table('orders')->where('id', $orderId)->value('user_id'));
        // By user too: the shared DB has instalment rows left behind by deleted orders, at ids a test order can reuse.
        $this->assertSame(2, DB::table('order_instalments')->where('order_id', $orderId)->where('user_id', $user->id)->count());
        $kept = CreditAssessment::find($assessment->id);
        $this->assertNotNull($kept);
        $this->assertNull($kept->employer_name);
    }

    public function test_deletion_is_limited_to_five_tries_a_minute(): void
    {
        $token = $this->tokenFor($this->makeCustomer());

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/v1/me/delete', ['password' => 'wrong'])->assertUnprocessable();
        }

        $this->withToken($token)->postJson('/api/v1/me/delete', ['password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_app_config_carries_the_store_links(): void
    {
        config(['atompay.api.links' => ['privacy_url' => null, 'terms_url' => null, 'account_deletion_url' => 'https://atompay.shop/account/delete']]);

        $this->getJson('/api/v1/app-config')
            ->assertJsonPath('data.privacy_url', route('privacy'))
            ->assertJsonPath('data.terms_url', null)
            ->assertJsonPath('data.account_deletion_url', 'https://atompay.shop/account/delete');

        $this->get('/privacy-policy')->assertRedirect('/privacy');
    }
}
