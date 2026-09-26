<?php

namespace Tests\Feature\Api;

use App\Mail\CustomerAlertMail;
use App\Mail\WelcomeMail;
use App\Models\CustomerNotification;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailAlertsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function sweep(): void
    {
        $this->artisan('atompay:notify', ['--now' => now('Asia/Karachi')->setTime(12, 0)->toIso8601String()])->assertSuccessful();
    }

    public function test_every_notification_is_also_emailed_with_a_one_click_unsubscribe(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user, 'approved', 75000);

        $this->sweep();

        Mail::assertSent(CustomerAlertMail::class, function (CustomerAlertMail $m) use ($user) {
            $headers = $m->headers()->text;

            return $m->hasTo($user->email)
                && $m->title === 'Your AtomPay limit is approved'
                && str_contains($m->body, 'PKR 75,000')
                && $m->actionUrl === route('account.dashboard')
                && str_contains($headers['List-Unsubscribe'], '/email/alerts/'.$user->id.'/off')
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
        $this->assertNotNull(CustomerNotification::where('user_id', $user->id)->sole()->emailed_at);
    }

    public function test_the_alert_email_renders(): void
    {
        $user = $this->makeCustomer(['name' => 'Ayesha Khan']);
        $n = CustomerNotification::create(['user_id' => $user->id, 'type' => 'instalment_overdue', 'title' => 'Instalment overdue',
            'body' => 'PKR 12,500 was due on 5 Oct.', 'data' => ['screen' => 'plan'], 'dedupe_key' => "t:{$user->id}"]);

        $html = (new CustomerAlertMail($n, $user))->render();

        $this->assertStringContainsString('Hi Ayesha K.', $html);
        $this->assertStringContainsString('Action needed', $html);
        $this->assertStringContainsString('View my payments', $html);
        $this->assertStringContainsString('Turn off alert emails', $html);
    }

    public function test_a_submitted_application_is_acknowledged(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $this->withToken($this->tokenFor($user))->postJson('/api/v1/application', [
            'employment_status' => 'retired', 'income_source' => 'pension', 'monthly_income' => 80000,
        ])->assertCreated();

        $this->sweep();

        $this->assertSame('application_received', CustomerNotification::where('user_id', $user->id)->sole()->type);
        Mail::assertSent(CustomerAlertMail::class, fn ($m) => $m->title === "We've received your application");
    }

    public function test_customers_who_turned_alerts_off_get_the_inbox_but_no_email(): void
    {
        $user = $this->makeCustomer();
        UserPreference::create(['user_id' => $user->id, 'email_alerts' => false]);
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user);

        $this->sweep();

        $this->assertSame(1, CustomerNotification::where('user_id', $user->id)->count());
        Mail::assertNotSent(CustomerAlertMail::class);
    }

    public function test_the_signed_unsubscribe_link_works_and_an_unsigned_one_does_not(): void
    {
        $user = $this->makeCustomer();

        $this->get("/email/alerts/{$user->id}/off")->assertForbidden();

        $this->get(URL::signedRoute('email.alerts.off', ['user' => $user->id]))
            ->assertOk()->assertSee('Alert emails are off');
        $this->assertFalse($user->fresh()->wantsEmailAlerts());

        $this->post(URL::signedRoute('email.alerts.on', ['user' => $user->id]))->assertOk()->assertSee('back on');
        $this->assertTrue($user->fresh()->wantsEmailAlerts());

        // RFC 8058 one-click: a mail client POSTs with no CSRF token.
        $this->post(URL::signedRoute('email.alerts.off', ['user' => $user->id]), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
        $this->assertFalse($user->fresh()->wantsEmailAlerts());
    }

    public function test_the_app_reads_and_changes_the_email_preference(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/me/preferences')->assertExactJson(['data' => ['email_alerts' => true]]);
        $this->freshRequest()->withToken($token)->patchJson('/api/v1/me/preferences', ['email_alerts' => false])
            ->assertExactJson(['data' => ['email_alerts' => false]]);
        $this->assertFalse($user->fresh()->wantsEmailAlerts());

        $this->freshRequest()->withToken($token)->patchJson('/api/v1/me/preferences', ['email_alerts' => 'maybe'])->assertUnprocessable();
    }

    // The welcome email is covered in SignupTest (sent only once sign-up is verified).
}
