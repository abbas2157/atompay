<?php

namespace Tests\Feature\Api;

use App\Mail\CustomerAlertMail;
use App\Mail\SignupCodeMail;
use App\Mail\WelcomeMail;
use App\Models\PendingSignup;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Sign-up with ONE contact: an email gets its code by email, a mobile gets
 * it on WhatsApp. The account only exists once that code is proven.
 */
class SignupTest extends ApiTestCase
{
    /** @var list<string> WhatsApp codes, in the order they were sent */
    private array $whatsappCodes = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '1013']);
        Http::fake(['graph.facebook.com/*' => function ($request) {
            $this->whatsappCodes[] = $request['template']['components'][0]['parameters'][0]['text'];

            return Http::response(['messages' => [['id' => 'wamid.'.count($this->whatsappCodes)]]]);
        }]);
    }

    private function details(string $login, array $overrides = []): array
    {
        return ['name' => 'Ayesha Khan', 'login' => $login, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, ...$overrides];
    }

    private function emailCode(): string
    {
        $codes = [];
        Mail::assertSent(SignupCodeMail::class, function (SignupCodeMail $m) use (&$codes) {
            $codes[] = $m->code;

            return true;
        });

        return end($codes);
    }

    public function test_an_email_sign_up_gets_its_code_by_email_only(): void
    {
        $start = $this->postJson('/api/v1/auth/register', $this->details('Ayesha.Signup@Example.test'))
            ->assertStatus(202)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.destination', 'ay***********@example.test')
            ->assertJsonPath('data.resend_in', 60)
            ->json('data');

        $this->assertNull(User::where('email', 'ayesha.signup@example.test')->first());   // no account yet
        Http::assertNothingSent();                                                         // no WhatsApp
        Mail::assertNotSent(WelcomeMail::class);

        $response = $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $start['signup_id'], 'code' => $this->emailCode(), 'device_name' => 'Pixel 7'])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'ayesha.signup@example.test')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.phone', null);

        $this->assertStringStartsWith('atompay_', explode('|', $response->json('data.token'))[1]);

        $user = User::where('email', 'ayesha.signup@example.test')->sole();
        $this->assertTrue($user->isCustomer());
        $this->assertTrue(password_verify(self::PASSWORD, $user->password));   // hashed once, not twice
        $this->assertSame('Pixel 7', $user->tokens()->sole()->name);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo('ayesha.signup@example.test'));

        // A sign-up completes once.
        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $start['signup_id'], 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors(['signup']);
    }

    public function test_a_mobile_sign_up_gets_its_code_on_whatsapp_only(): void
    {
        $start = $this->postJson('/api/v1/auth/register', $this->details('+92 399 7654321'))
            ->assertStatus(202)
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.destination', '0399*****21')
            ->json('data');

        Http::assertSent(fn ($r) => $r['to'] === '923997654321' && $r['template']['name'] === 'auth_otp');
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $start['signup_id'], 'code' => $this->whatsappCodes[0]])
            ->assertCreated()
            ->assertJsonPath('data.user.phone', '03997654321')
            ->assertJsonPath('data.user.email', null)            // placeholder hidden
            ->assertJsonPath('data.user.email_verified', false);

        $user = User::where('phone', '03997654321')->sole();
        $this->assertSame('03997654321@no-email.atompay.shop', $user->email);
        $this->assertFalse($user->hasRealEmail());
        Mail::assertNothingSent();                                // no welcome to a placeholder

        // They sign in with the mobile.
        $this->postJson('/api/v1/auth/login', ['login' => '0399 7654321', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_a_mobile_only_customer_is_never_emailed_alerts(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->details('03997654321'))->json('data.signup_id');
        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $this->whatsappCodes[0]])->assertCreated();
        $user = User::where('phone', '03997654321')->sole();
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user);

        $this->artisan('atompay:notify', ['--now' => now('Asia/Karachi')->setTime(12, 0)->toIso8601String()])->assertSuccessful();

        $this->assertSame(1, $user->appNotifications()->count());   // inbox still works
        Mail::assertNotSent(CustomerAlertMail::class);
    }

    public function test_the_contact_is_validated_before_any_code_is_sent(): void
    {
        $existing = $this->makeCustomer(['phone' => '03991112233']);

        $this->postJson('/api/v1/auth/register', $this->details($existing->email))
            ->assertJsonPath('errors.login.0', 'An account with this email already exists. Sign in or reset your password.');
        $this->postJson('/api/v1/auth/register', $this->details('+923991112233'))   // same number, other format
            ->assertJsonPath('errors.login.0', 'An account with this mobile number already exists. Sign in or reset your password.');
        $this->postJson('/api/v1/auth/register', $this->details('021 1234567'))
            ->assertJsonPath('errors.login.0', 'That looks like a landline. Enter a mobile number so we can text you about payments.');
        $this->postJson('/api/v1/auth/register', $this->details('ayesha'))
            ->assertJsonPath('errors.login.0', 'Enter your email address or mobile number, e.g. 0300 1234567.');
        $this->postJson('/api/v1/auth/register', $this->details('x@no-email.atompay.shop'))
            ->assertJsonPath('errors.login.0', 'Enter a valid email address.');
        // (Password rules are checked in AuthApiTest - a 6th call here would hit the 5/hour sign-up limit.)

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(0, PendingSignup::count());
    }

    public function test_wrong_codes_count_down_and_five_end_the_sign_up(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->details('new@example.test'))->json('data.signup_id');
        $right = $this->emailCode();
        $wrong = $right === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $wrong])
            ->assertJsonPath('errors.code.0', "That code isn't right. 4 tries left.");
        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $wrong]);
        }
        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $wrong])
            ->assertJsonPath('errors.signup.0', 'Too many wrong codes. Please start again.');

        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $right])->assertJsonValidationErrors(['signup']);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->details('new@example.test'))->json('data.signup_id');

        $this->travel(11)->minutes();

        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $this->emailCode()])
            ->assertJsonPath('errors.code.0', 'This code has expired. Send a new one.');
    }

    public function test_resend_waits_for_the_cooldown_and_replaces_the_code(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->details('03997654321'))->json('data.signup_id');

        $this->postJson('/api/v1/auth/register/resend', ['signup_id' => $id])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/register/resend', ['signup_id' => $id])->assertOk()->assertJsonPath('data.resend_in', 60);

        $this->assertCount(2, $this->whatsappCodes);
        if ($this->whatsappCodes[0] !== $this->whatsappCodes[1]) {
            $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $this->whatsappCodes[0]])->assertJsonValidationErrors(['code']);
        }
        $this->postJson('/api/v1/auth/register/verify', ['signup_id' => $id, 'code' => $this->whatsappCodes[1]])->assertCreated();
    }

    public function test_a_number_gets_at_most_three_codes_an_hour(): void
    {
        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/auth/register', $this->details('03997654321'))->assertStatus(202);
        }

        $this->postJson('/api/v1/auth/register', $this->details('03997654321'))
            ->assertJsonPath('errors.login.0', 'Too many codes have been sent to this number. Please try again in an hour.');
        $this->assertCount(3, $this->whatsappCodes);
    }

    public function test_a_number_without_whatsapp_is_a_field_error_and_leaves_nothing_behind(): void
    {
        // Replace setUp's success stub (Http::fake keeps the first match).
        Http::swap(new HttpFactory);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Recipient not on WhatsApp', 'code' => 131026]], 400)]);

        $this->postJson('/api/v1/auth/register', $this->details('03997654321'))
            ->assertJsonPath('errors.login.0', "We couldn't send a WhatsApp code to this number. Make sure it has WhatsApp, or sign up with your email.");

        $this->assertSame(0, PendingSignup::count());
    }

    public function test_without_whatsapp_in_production_only_email_sign_up_works(): void
    {
        $this->app['env'] = 'production';
        config(['services.whatsapp.token' => null]);

        $this->postJson('/api/v1/auth/register', $this->details('03997654321'))
            ->assertJsonPath('errors.login.0', "We can't send codes to mobile numbers right now. Sign up with your email address instead.");
        $this->postJson('/api/v1/auth/register', $this->details('new@example.test'))->assertStatus(202);

        $this->getJson('/api/v1/app-config')->assertJsonPath('data.features.signup_channels', ['email']);
    }

    public function test_the_website_signs_up_by_email(): void
    {
        $this->post('/register', $this->details('web-signup@example.test'))->assertRedirect(route('register.verify'));
        $this->assertNull(User::where('email', 'web-signup@example.test')->first());

        $this->get('/register/verify')->assertOk()->assertSee('Enter your code')->assertSee('by email to');

        $this->post('/register/verify', ['code' => $this->emailCode()])->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'web-signup@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_website_signs_up_by_mobile(): void
    {
        $this->post('/register', $this->details('0399 1112233'))->assertRedirect(route('register.verify'));

        $this->get('/register/verify')->assertOk()->assertSee('on WhatsApp to')->assertSee('0399*****33');

        $this->post('/register/verify', ['code' => $this->whatsappCodes[0]])->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs(User::where('phone', '03991112233')->sole());
    }

    public function test_the_website_verify_page_needs_a_sign_up_in_progress(): void
    {
        $this->get('/register/verify')->assertRedirect(route('register'));
    }

    public function test_old_pending_sign_ups_are_pruned(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->details('new@example.test'))->json('data.signup_id');

        $this->travel(25)->hours();
        $this->artisan('model:prune', ['--model' => [PendingSignup::class]])->assertSuccessful();

        $this->assertNull(PendingSignup::where('public_id', $id)->first());
    }
}
