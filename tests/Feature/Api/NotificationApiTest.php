<?php

namespace Tests\Feature\Api;

use App\Models\CustomerNotification;
use App\Models\Device;
use App\Models\Enums\VerificationStatus;
use Illuminate\Support\Facades\Http;

class NotificationApiTest extends ApiTestCase
{
    /** Noon in Pakistan - inside reminder hours. */
    private function pktNoon(): string
    {
        return now('Asia/Karachi')->setTime(12, 0)->toIso8601String();
    }

    public function test_app_config_is_public(): void
    {
        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['min_version' => ['android', 'ios'], 'store_url', 'shop_url', 'password_reset_url', 'support', 'features' => ['push']]])
            ->assertJsonPath('data.features.push', false);
    }

    public function test_a_device_registers_moves_between_accounts_and_unregisters(): void
    {
        $first = $this->makeCustomer();
        $this->withToken($this->tokenFor($first))
            ->postJson('/api/v1/devices', ['fcm_token' => 'fcm-abc', 'platform' => 'android', 'app_version' => '1.0.0'])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        // Same phone, someone else signs in: the device follows the new account.
        $second = $this->makeCustomer();
        $secondToken = $this->tokenFor($second);
        $this->freshRequest()->withToken($secondToken)
            ->postJson('/api/v1/devices', ['fcm_token' => 'fcm-abc', 'platform' => 'android'])
            ->assertOk();

        $this->assertSame(0, $first->devices()->count());
        $this->assertSame(1, $second->devices()->count());

        $this->freshRequest()->withToken($secondToken)->deleteJson('/api/v1/devices', ['fcm_token' => 'fcm-abc'])->assertNoContent();
        $this->assertSame(0, Device::count() - Device::where('user_id', '!=', $second->id)->count());

        $this->freshRequest()->withToken($secondToken)->postJson('/api/v1/devices', ['platform' => 'windows'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fcm_token', 'platform']);
    }

    public function test_signing_out_stops_that_phones_pushes(): void
    {
        $user = $this->makeCustomer();
        $phone = $this->tokenFor($user);
        $tablet = $this->tokenFor($user);

        $this->withToken($phone)->postJson('/api/v1/devices', ['fcm_token' => 'fcm-phone', 'platform' => 'android'])->assertCreated();
        $this->freshRequest()->withToken($tablet)->postJson('/api/v1/devices', ['fcm_token' => 'fcm-tablet', 'platform' => 'ios'])->assertCreated();

        $this->freshRequest()->withToken($phone)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['ios'], $user->devices()->pluck('platform')->all());
    }

    public function test_sessions_list_this_and_other_devices_and_can_end_one(): void
    {
        $user = $this->makeCustomer();
        $phone = $this->tokenFor($user);
        $this->tokenFor($user);
        $user->createToken('expired', ['*'], now()->subDay());

        $sessions = $this->withToken($phone)->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.token')
            ->json('data');

        $other = collect($sessions)->firstWhere('current', false);
        $this->assertNotNull(collect($sessions)->firstWhere('current', true));

        $this->freshRequest()->withToken($phone)->deleteJson("/api/v1/auth/sessions/{$other['id']}")->assertNoContent();
        $this->freshRequest()->withToken($phone)->getJson('/api/v1/auth/sessions')->assertJsonCount(1, 'data');

        // Someone else's session id is simply not found.
        $stranger = $this->makeCustomer();
        $this->freshRequest()->withToken($this->tokenFor($stranger))->deleteJson("/api/v1/auth/sessions/{$sessions[0]['id']}")->assertNotFound();
    }

    public function test_the_sweep_announces_decisions_and_reminders_exactly_once(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $assessment = $this->makeDecidedAssessment($user, 'approved', 75000);
        $orderId = $this->makeOrder($user, [
            [now('Asia/Karachi')->addDays(3), 12500, false],
            [now('Asia/Karachi')->subDay(), 12500, false],
        ]);

        $this->artisan('atompay:notify', ['--now' => $this->pktNoon()])->assertSuccessful();
        $this->artisan('atompay:notify', ['--now' => $this->pktNoon()])->assertSuccessful();   // idempotent

        $sent = CustomerNotification::where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame(['limit_decided', 'instalment_due', 'instalment_overdue'], $sent->pluck('type')->all());
        $this->assertSame('Your AtomPay limit is approved', $sent[0]->title);
        $this->assertStringContainsString('PKR 75,000', $sent[0]->body);
        $this->assertSame(['screen' => 'dashboard', 'assessment_id' => $assessment->id], $sent[0]->data);
        $this->assertSame('Instalment due in 3 days', $sent[1]->title);
        $this->assertSame($orderId, $sent[2]->data['order_id']);
    }

    public function test_reminders_wait_for_waking_hours(): void
    {
        $user = $this->makeCustomer();
        $this->makeOrder($user, [[now('Asia/Karachi')->addDays(3), 12500, false]]);

        $this->artisan('atompay:notify', ['--now' => now('Asia/Karachi')->setTime(2, 0)->toIso8601String()])->assertSuccessful();

        $this->assertSame(0, CustomerNotification::where('user_id', $user->id)->count());
    }

    public function test_kyc_outcomes_are_announced(): void
    {
        $user = $this->makeCustomer();
        $profile = $this->makeProfile($user, VerificationStatus::Rejected);
        $profile->forceFill(['verification_notes' => 'The address could not be found.'])->save();

        $this->artisan('atompay:notify', ['--now' => $this->pktNoon()])->assertSuccessful();

        $n = CustomerNotification::where('user_id', $user->id)->sole();
        $this->assertSame('kyc_rejected', $n->type);
        $this->assertSame('The address could not be found.', $n->body);
    }

    public function test_the_inbox_lists_counts_and_marks_read(): void
    {
        $user = $this->makeCustomer();
        $other = $this->makeCustomer();
        foreach ([1, 2, 3] as $i) {
            CustomerNotification::create(['user_id' => $user->id, 'type' => 'instalment_due', 'title' => "T{$i}", 'body' => 'B', 'data' => ['screen' => 'plan'], 'dedupe_key' => "test:{$user->id}:{$i}"]);
        }
        $foreign = CustomerNotification::create(['user_id' => $other->id, 'type' => 'instalment_due', 'title' => 'X', 'body' => 'B', 'dedupe_key' => "test:{$other->id}"]);
        $token = $this->tokenFor($user);

        $list = $this->withToken($token)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'T3')             // newest first
            ->assertJsonPath('data.0.payload.screen', 'plan')
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonPath('meta.per_page', 20)
            ->json('data');

        $this->freshRequest()->withToken($token)->postJson("/api/v1/notifications/{$list[0]['id']}/read")
            ->assertOk()->assertJsonPath('data.read', true);
        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')->assertJsonPath('data.unread_notifications', 2);

        $this->freshRequest()->withToken($token)->postJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();

        $this->freshRequest()->withToken($token)->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->freshRequest()->withToken($token)->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 0);
    }

    public function test_push_goes_to_every_device_and_dead_tokens_are_dropped(): void
    {
        // A throwaway service account, so the JWT signing path really runs.
        // Windows PHP builds (XAMPP) ship openssl.cnf but don't point OpenSSL at it.
        $cnf = array_filter(['config' => getenv('OPENSSL_CONF') ?: (is_file($f = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf') ? $f : null)]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, ...$cnf]);
        openssl_pkey_export($key, $pem, null, $cnf);
        $file = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($file, json_encode([
            'project_id' => 'atompay-test', 'client_email' => 'push@atompay-test.iam.gserviceaccount.com',
            'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $file]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function ($request) {
                return $request['message']['token'] === 'dead-token'
                    ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/atompay-test/messages/1']);
            },
        ]);

        $user = $this->makeCustomer();
        foreach (['live-token', 'dead-token'] as $t) {
            Device::create(['user_id' => $user->id, 'fcm_token' => $t, 'fcm_token_hash' => Device::hashToken($t), 'platform' => 'android']);
        }
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user);

        $this->artisan('atompay:notify', ['--now' => $this->pktNoon()])->assertSuccessful();

        $this->assertNotNull(CustomerNotification::where('user_id', $user->id)->sole()->pushed_at);
        $this->assertSame(['live-token'], $user->devices()->pluck('fcm_token')->all());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'projects/atompay-test/messages:send')
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['notification']['title'] === 'Your AtomPay limit is approved'
            && $r['message']['data']['screen'] === 'dashboard');

        unlink($file);
    }
}
