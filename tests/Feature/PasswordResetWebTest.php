<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetWebTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sign_in_links_to_forgot_password(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'), false);
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password')->assertSee('noindex', false);
    }

    public function test_a_customer_resets_their_password_on_the_website(): void
    {
        Mail::fake();
        $user = app(AccountService::class)->registerCustomer([
            'name' => 'Web Reset', 'phone' => '0399'.random_int(1000000, 9999999),
            'email' => 'web-'.Str::lower(Str::random(8)).'@example.test', 'password' => 'old-password-1',
        ]);

        $this->post('/forgot-password', ['login' => $user->email])->assertRedirect(route('password.verify'));
        $this->get('/forgot-password/verify')->assertOk()->assertSee('Enter your code')->assertSee("we've emailed a code");

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->post('/forgot-password/verify', ['code' => '999999' === $code ? '000000' : '999999'])
            ->assertSessionHasErrors('code');
        $this->post('/forgot-password/verify', ['code' => $code])->assertRedirect(route('password.reset'));

        $this->get('/reset-password')->assertOk()->assertSee('Choose a new password');
        $this->post('/reset-password', ['password' => 'fresh-password-2', 'password_confirmation' => 'fresh-password-2'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(password_verify('fresh-password-2', $user->fresh()->password));
        Mail::assertSent(PasswordChangedMail::class);
    }

    public function test_the_steps_cannot_be_skipped(): void
    {
        $this->get('/forgot-password/verify')->assertRedirect(route('password.request'));
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }
}
