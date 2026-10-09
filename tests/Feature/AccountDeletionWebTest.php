<?php

namespace Tests\Feature;

use App\Mail\AccountDeletedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\ApiTestCase;

/** The website's delete-account pages - the link given to the app stores. */
class AccountDeletionWebTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake(config('atompay.kyc.disk'));
    }

    public function test_the_public_page_explains_deletion_and_leads_to_sign_in(): void
    {
        $this->get('/delete-account')->assertOk()
            ->assertSee('Delete your')
            ->assertSee('What we keep')
            ->assertSee(route('account.delete'));

        $this->get(route('account.delete'))->assertRedirect(route('login'));
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.account_deletion_url', route('account.delete.info'));
    }

    public function test_a_customer_deletes_their_account_on_the_website(): void
    {
        $user = $this->makeCustomer();
        $email = $user->email;
        $token = $this->tokenFor($user);

        $this->actingAs($user)->get(route('account.delete'))->assertOk()->assertSee('Delete my account');

        $this->actingAs($user)->post(route('account.delete.perform'), ['password' => self::PASSWORD, 'confirm' => '1'])
            ->assertRedirect(route('account.delete.info'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $row = User::find($user->id);
        $this->assertSame('block', $row->status);
        $this->assertSame('Deleted user', $row->name);
        $this->assertSame(0, $user->tokens()->count());
        $this->freshRequest()->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame('web', DB::table('atompay_account_deletions')->where('user_id', $user->id)->value('source'));
        Mail::assertQueued(AccountDeletedMail::class, fn (AccountDeletedMail $m) => $m->hasTo($email));
    }

    public function test_the_wrong_password_or_no_confirmation_deletes_nothing(): void
    {
        $user = $this->makeCustomer();

        $this->actingAs($user)->from(route('account.delete'))
            ->post(route('account.delete.perform'), ['password' => 'not-it', 'confirm' => '1'])
            ->assertRedirect(route('account.delete'))
            ->assertSessionHasErrors(['password' => "That password isn't right."]);

        $this->actingAs($user)->post(route('account.delete.perform'), ['password' => self::PASSWORD])
            ->assertSessionHasErrors(['confirm']);

        $this->assertSame('active', $user->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_money_owed_blocks_deletion_on_the_website_too(): void
    {
        $user = $this->makeCustomer();
        $this->makeOrder($user, [['2026-09-01', 10000, true], ['2026-11-01', 10000, false]]);

        $this->actingAs($user)->get(route('account.delete'))->assertOk()
            ->assertSee('You still have instalments to pay.')
            ->assertDontSee('name="password"', false);

        $this->actingAs($user)->post(route('account.delete.perform'), ['password' => self::PASSWORD, 'confirm' => '1'])
            ->assertSessionHasErrors('password');

        $this->assertSame('active', $user->fresh()->status);
    }
}
