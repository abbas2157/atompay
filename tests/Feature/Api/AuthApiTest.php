<?php

namespace Tests\Feature\Api;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthApiTest extends ApiTestCase
{
    // Sign-up (OTP) is covered in SignupTest.

    public function test_registration_reports_field_errors_as_json(): void
    {
        $existing = $this->makeCustomer();

        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'login' => $existing->email,
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'login', 'password']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone', 'login' => 'new@example.test', 'password' => 'long-enough-1', 'password_confirmation' => 'different-1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['login']);
    }

    public function test_a_customer_signs_in_with_email_or_any_phone_format(): void
    {
        $user = $this->makeCustomer(['phone' => '03991234567']);

        foreach ([$user->email, '03991234567', '+92 399 1234567'] as $login) {
            $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => self::PASSWORD])
                ->assertOk()
                ->assertJsonPath('data.user.id', $user->id)
                ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user']]);
        }

        $this->assertSame(3, $user->tokens()->count());
        $this->assertSame('AtomPay app', $user->tokens()->first()->name);   // default device name
    }

    public function test_a_wrong_password_is_a_validation_error_not_a_token(): void
    {
        $user = $this->makeCustomer();

        $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_staff_and_blocked_accounts_are_not_issued_a_token(): void
    {
        $staff = $this->makeCustomer();
        $staff->forceFill(['role' => 'admin'])->save();

        $blocked = $this->makeCustomer();
        $blocked->forceFill(['status' => 'block'])->save();

        foreach ([$staff, $blocked] as $user) {
            $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => self::PASSWORD])
                ->assertForbidden()
                ->assertJsonPath('message', 'Please sign in with an AtomShop customer account.');

            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_protected_endpoints_need_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');

        // JSON even when the client forgets the Accept header - never a redirect to /login.
        $this->get('/api/v1/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_me_returns_the_signed_in_account(): void
    {
        $user = $this->makeCustomer(['name' => 'Ayesha Khan', 'phone' => '03991112222']);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.short_name', 'Ayesha K.')
            ->assertJsonPath('data.phone_formatted', '0399 1112222')
            ->assertJsonPath('data.kyc_status', 'not_started')
            ->assertJsonMissingPath('data.password');
    }

    public function test_logout_revokes_only_this_device(): void
    {
        $user = $this->makeCustomer();
        $phone = $this->tokenFor($user);
        $tablet = $this->tokenFor($user);

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->freshRequest()->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
        $this->freshRequest()->withToken($tablet)->getJson('/api/v1/me')->assertOk();
    }

    public function test_logout_all_revokes_every_device(): void
    {
        $user = $this->makeCustomer();
        $phone = $this->tokenFor($user);
        $tablet = $this->tokenFor($user);

        $this->withToken($phone)->postJson('/api/v1/auth/logout-all')->assertNoContent();

        $this->freshRequest()->withToken($tablet)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_expired_token_is_refused(): void
    {
        $user = $this->makeCustomer();
        $token = $user->createToken('old phone', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_an_atomshop_app_token_does_not_open_atompay(): void
    {
        // Same user, same class name, but issued by AtomShop into its own table.
        $user = $this->makeCustomer();
        $id = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'atomshop-app',
            'token' => hash('sha256', 'atomshop-secret'),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($id.'|atomshop-secret')->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_customer_blocked_after_sign_in_loses_their_token(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);
        $user->forceFill(['status' => 'block'])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }
}
