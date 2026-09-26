<?php

namespace Tests\Feature\Api;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PasswordResetApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function emailedCode(): string
    {
        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_the_full_email_flow_resets_the_password_and_signs_in(): void
    {
        $user = $this->makeCustomer();
        $oldToken = $this->tokenFor($user);

        $request = $this->postJson('/api/v1/auth/password/forgot', ['login' => strtoupper($user->email)])
            ->assertStatus(202)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.resend_in', 60)
            ->json('data');

        $this->assertStringContainsString('*', $request['destination']);
        $code = $this->emailedCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $resetToken = $this->postJson('/api/v1/auth/password/verify', ['request_id' => $request['request_id'], 'code' => $code])
            ->assertOk()
            ->json('data.reset_token');

        $this->postJson('/api/v1/auth/password/reset', [
            'reset_token' => $resetToken, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass', 'device_name' => 'Pixel',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);

        // Old sign-ins are gone, the new password works, and the owner is told.
        $this->freshRequest()->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->freshRequest()->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'brand-new-pass'])->assertOk();
        Mail::assertSent(PasswordChangedMail::class, fn ($m) => $m->hasTo($user->email));

        // The token works once.
        $this->postJson('/api/v1/auth/password/reset', ['reset_token' => $resetToken, 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reset_token']);
    }

    public function test_a_mobile_number_gets_the_code_on_whatsapp_via_atomshops_template(): void
    {
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '1013']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        // Stored the way some AtomShop rows are: +92 format.
        $user = $this->makeCustomer(['phone' => '+923995550001']);

        $request = $this->postJson('/api/v1/auth/password/forgot', ['login' => '0399 5550001'])
            ->assertStatus(202)
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.destination', '0399*****01')
            ->json('data');

        $code = null;
        Http::assertSent(function ($r) use (&$code) {
            $code = $r['template']['components'][0]['parameters'][0]['text'];

            return $r->url() === 'https://graph.facebook.com/v19.0/1013/messages'
                && $r->hasHeader('Authorization', 'Bearer test-token')
                && $r['to'] === '923995550001'
                && $r['template']['name'] === 'auth_otp'
                && $r['template']['language']['code'] === 'en_US'
                && $r['template']['components'][1]['parameters'][0]['text'] === $code;   // copy-code button
        });
        Mail::assertNotSent(PasswordResetCodeMail::class);

        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $request['request_id'], 'code' => $code])->assertOk();
        $this->assertSame($user->id, PasswordReset::where('public_id', $request['request_id'])->value('user_id'));
    }

    public function test_an_unknown_account_gets_the_same_answer_and_no_message(): void
    {
        $real = $this->makeCustomer();
        $realShape = array_keys($this->postJson('/api/v1/auth/password/forgot', ['login' => $real->email])->json('data'));
        Mail::fake();   // forget the real one

        $response = $this->postJson('/api/v1/auth/password/forgot', ['login' => 'nobody-here@example.test'])
            ->assertStatus(202)
            ->assertJsonPath('data.channel', 'email');

        $this->assertSame($realShape, array_keys($response->json('data')));
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $response->json('data.request_id'), 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_staff_accounts_cannot_reset_through_atompay(): void
    {
        $staff = $this->makeCustomer();
        $staff->forceFill(['role' => 'admin'])->save();

        $this->postJson('/api/v1/auth/password/forgot', ['login' => $staff->email])->assertStatus(202);

        Mail::assertNothingSent();
    }

    public function test_five_wrong_codes_burn_the_code(): void
    {
        $user = $this->makeCustomer();
        $id = $this->postJson('/api/v1/auth/password/forgot', ['login' => $user->email])->json('data.request_id');
        $code = $this->emailedCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $id, 'code' => $wrong])
            ->assertJsonPath('errors.code.0', "That code isn't right. 4 tries left.");
        foreach (range(1, 4) as $_) {
            $this->postJson('/api/v1/auth/password/verify', ['request_id' => $id, 'code' => $wrong]);
        }

        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $id, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This code has expired. Request a new one.');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->makeCustomer();
        $id = $this->postJson('/api/v1/auth/password/forgot', ['login' => $user->email])->json('data.request_id');
        $code = $this->emailedCode();

        $this->travel(11)->minutes();

        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $id, 'code' => $code])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'This code has expired. Request a new one.');
    }

    public function test_asking_again_inside_the_cooldown_keeps_the_same_code(): void
    {
        $user = $this->makeCustomer();

        $first = $this->postJson('/api/v1/auth/password/forgot', ['login' => $user->email])->json('data');
        $second = $this->postJson('/api/v1/auth/password/forgot', ['login' => $user->email])->json('data');

        $this->assertSame($first['request_id'], $second['request_id']);
        $this->assertLessThanOrEqual(60, $second['resend_in']);
        Mail::assertSentCount(1);

        // After the cooldown a new code replaces the old one.
        $this->travel(61)->seconds();
        $oldCode = $this->emailedCode();
        $third = $this->postJson('/api/v1/auth/password/forgot', ['login' => $user->email])->json('data');
        $this->assertNotSame($first['request_id'], $third['request_id']);
        $this->postJson('/api/v1/auth/password/verify', ['request_id' => $first['request_id'], 'code' => $oldCode])->assertUnprocessable();
    }

    public function test_input_that_is_neither_email_nor_mobile_is_a_field_error(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', ['login' => '021 1234567'])
            ->assertUnprocessable()->assertJsonValidationErrors(['login']);
    }

    public function test_whatsapp_is_refused_in_production_when_not_configured(): void
    {
        $this->app['env'] = 'production';
        config(['services.whatsapp.token' => null]);

        $this->postJson('/api/v1/auth/password/forgot', ['login' => '03001234567'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', "Codes by WhatsApp aren't available right now. Enter your email address instead.");

        $this->getJson('/api/v1/app-config')->assertJsonPath('data.features.password_reset_channels', ['email']);
    }

    public function test_me_no_longer_exposes_the_uuid(): void
    {
        $user = $this->makeCustomer();

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('data.uuid');
    }
}
