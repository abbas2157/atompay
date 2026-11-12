<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\VerificationStatus;
use App\Models\KycAccessLog;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The core process end to end:
 * KYC → Address Verification → Income Assessment → Risk Assessment → Purchase Limit.
 * Runs on the shared AtomShop DB inside a transaction; uploads go to a fake disk.
 */
class KycAssessmentFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return User::customers()->active()->doesntHave('kycProfile')->firstOrFail();
    }

    private function staff(): User
    {
        return User::whereIn('role', config('atompay.staff_roles'))->where('status', 'active')->firstOrFail();
    }

    private function application(array $overrides = []): array
    {
        return [
            'full_name' => 'Test Customer',
            'cnic' => '42101-1234567-1',
            'mobile' => '0300 1234567',
            'date_of_birth' => '1990-05-01',
            'residential_address' => 'House 1, Street 2, Karachi',
            'cnic_front' => UploadedFile::fake()->image('front.jpg'),
            'cnic_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'employment_status' => 'salaried',
            'employer_name' => 'Acme Ltd',
            'income_source' => 'salary',
            'monthly_income' => 200000,
            'existing_instalments' => 10000,
            'monthly_expenses' => 90000,
            ...$overrides,
        ];
    }

    public function test_customer_submits_kyc_and_financial_profile(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.application.store'), $this->application())
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHasNoErrors();

        $profile = KycProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('4210112345671', $profile->cnic);           // dashes stripped
        $this->assertSame('42101-1234567-1', $profile->cnic_formatted);
        $this->assertSame('03001234567', $profile->mobile);
        $this->assertTrue($profile->hasDocuments());
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        Storage::disk(config('atompay.kyc.disk'))->assertExists($profile->cnic_front_path);

        $a = CreditAssessment::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(100000, $a->disposable_income);           // 200k - 10k - 90k
        $this->assertSame(60000, $a->approved_limit);               // 30%
        $this->assertSame(20000, $a->max_instalment);               // min(10%, disposable)
        $this->assertSame(AssessmentStatus::Pending, $a->status);
        $this->assertNotNull($a->risk_score);

        // Provisional limit is not spendable until staff decide. The wording
        // below is v1's dashboard; v2's overview is covered in DesignSwitchTest.
        config(['design.default' => 'v1', 'design.active' => 'v1']);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()->assertSee('Address verification pending')->assertSee('Not set yet');
    }

    public function test_low_disposable_income_caps_the_instalment(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.application.store'), $this->application([
            'monthly_income' => 100000, 'existing_instalments' => 50000, 'monthly_expenses' => 45000,
        ]))->assertSessionHasNoErrors();

        $a = CreditAssessment::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(5000, $a->disposable_income);
        $this->assertSame(5000, $a->max_instalment);                // capped below 10%
        $this->assertSame('medium', $a->risk_category->value);      // affordability + no history + unverified
    }

    public function test_validation_rejects_minors_bad_cnic_and_missing_documents(): void
    {
        $this->actingAs($this->customer())->post(route('account.application.store'), $this->application([
            'date_of_birth' => now()->subYears(17)->format('Y-m-d'),
            'cnic' => '123',
            'selfie' => null,
        ]))->assertSessionHasErrors(['date_of_birth', 'cnic', 'selfie']);
    }

    public function test_staff_verify_address_and_decide_limit(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $staff = $this->staff();

        $this->actingAs($user)->post(route('account.application.store'), $this->application());
        $assessment = CreditAssessment::where('user_id', $user->id)->firstOrFail();

        // A customer cannot reach the staff area.
        $this->actingAs($user)->get(route('staff.assessments.index'))->assertForbidden();

        $this->actingAs($staff)->get(route('staff.assessments.index'))
            ->assertOk()->assertSee($user->name);
        $this->actingAs($staff)->get(route('staff.assessments.show', $assessment))
            ->assertOk()->assertSee('Risk assessment')->assertSee('42101-1234567-1');

        // Section 2
        $this->actingAs($staff)->post(route('staff.assessments.address', $assessment), [
            'address_verified' => 1,
            'face_verified' => 1,
            'verified_at' => today()->format('Y-m-d'),
            'verification_status' => 'verified',
            'verification_notes' => 'Met at residence.',
            'verification_form' => UploadedFile::fake()->createWithContent('form.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF"),
        ])->assertSessionHasNoErrors();

        $profile = $user->fresh()->kycProfile;
        $this->assertTrue($profile->isVerified());
        $this->assertSame($staff->id, $profile->verified_by);
        Storage::disk(config('atompay.kyc.disk'))->assertExists($profile->verification_form_path);

        // Sections 4-5
        $this->actingAs($staff)->post(route('staff.assessments.decide', $assessment), [
            'credit_history' => 'good',
            'approved_limit' => 50000,
            'max_instalment' => 15000,
            'approved_tenure' => 6,
            'status' => 'approved',
            'notes' => 'Approved at reduced limit.',
        ])->assertRedirect(route('staff.assessments.index'))->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Approved, $assessment->status);
        $this->assertSame(50000, $assessment->approved_limit);
        $this->assertSame($staff->id, $assessment->decided_by);
        $this->assertSame('good', $assessment->credit_history->value);
        $this->assertGreaterThanOrEqual(70, $assessment->risk_score); // KYC verified + good history

        // The customer now sees the approved limit and can spend it. "PKR" is
        // v1's money prefix; v2 writes "Rs." and is covered in DesignSwitchTest.
        config(['design.default' => 'v1', 'design.active' => 'v1']);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()->assertSee('PKR 50,000')->assertSee('6 months')->assertSee('Approved at reduced limit.');
    }

    public function test_an_approved_customer_requests_a_limit_review_only_with_something_new(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $this->actingAs($user)->post(route('account.application.store'), $this->application());
        $approved = CreditAssessment::where('user_id', $user->id)->firstOrFail();
        $user->refresh()->kycProfile->forceFill(['verification_status' => VerificationStatus::Verified])->save();
        $approved->forceFill(['status' => AssessmentStatus::Approved, 'approved_limit' => 50000, 'decided_at' => now()])->save();
        $user->refresh(); // as the next request would see it

        $this->actingAs($user)->get(route('account.application'))
            ->assertOk()->assertSee('Request a limit review')->assertSee('PKR 50,000 limit stays active', false);

        // Same details, no new documents: nothing for staff to review.
        $same = collect($this->application())->except(['cnic_front', 'cnic_back', 'selfie'])->all();
        $this->actingAs($user)->post(route('account.application.store'), $same)->assertSessionHasErrors('monthly_income');
        $this->assertSame(1, CreditAssessment::where('user_id', $user->id)->count());

        $this->actingAs($user)->post(route('account.application.store'), [...$same, 'monthly_income' => 300000])
            ->assertRedirect(route('account.dashboard'))->assertSessionHas('status', fn ($s) => str_contains($s, 'limit stays active'));
        $this->assertSame($approved->id, $user->fresh()->activeAssessment->id);
        $this->assertTrue($user->fresh()->kycProfile->isVerified(), 'Income-only changes keep identity verified.');
    }

    public function test_documents_are_private_to_owner_and_staff(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $owner = $this->customer();
        $this->actingAs($owner)->post(route('account.application.store'), $this->application());
        $profile = $owner->fresh()->kycProfile;

        $other = User::customers()->active()->where('id', '!=', $owner->id)->firstOrFail();

        $url = route('documents.show', [$profile, 'selfie']);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($this->staff())->get($url)->assertOk();
        $this->actingAs($owner)->get($url)->assertOk();
    }

    /** Staff views of a customer's identity are logged; the customer's own are not. */
    public function test_staff_views_of_a_customers_identity_are_logged(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $owner = $this->customer();
        $this->actingAs($owner)->post(route('account.application.store'), $this->application());
        $profile = $owner->fresh()->kycProfile;
        $assessment = CreditAssessment::where('user_id', $owner->id)->latest('id')->firstOrFail();
        $staff = $this->staff();

        $this->actingAs($owner)->get(route('documents.show', [$profile, 'cnic-front']))->assertOk();
        $this->assertSame(0, KycAccessLog::where('subject_user_id', $owner->id)->count());

        $this->actingAs($staff)->get(route('documents.show', [$profile, 'cnic-front']))->assertOk();
        $this->actingAs($staff)->get(route('staff.assessments.show', $assessment))
            ->assertOk()
            ->assertSee("Who has viewed this customer's identity", false);

        $this->assertSame(
            ['cnic-front', 'review'],
            KycAccessLog::where('subject_user_id', $owner->id)->where('viewer_id', $staff->id)->orderBy('id')->pluck('item')->all(),
        );
    }

    public function test_an_image_carrying_code_is_refused(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $png = UploadedFile::fake()->image('front.png');
        $polyglot = UploadedFile::fake()->createWithContent('front.png', file_get_contents($png->getRealPath()).'<?php system($_GET["c"]); ?>');

        $this->actingAs($this->customer())
            ->post(route('account.application.store'), $this->application(['cnic_front' => $polyglot]))
            ->assertSessionHasErrors('cnic_front');
    }
    /* ------------------------------------------------- CNIC & mobile input */

    public function test_any_way_of_writing_the_cnic_and_mobile_is_accepted_and_stored_canonically(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.application.store'), $this->application([
            'cnic' => '35202-1234567-1',
            'mobile' => '+92 300 1234567',
        ]))->assertSessionHasNoErrors();

        $profile = KycProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('3520212345671', $profile->cnic);
        $this->assertSame('03001234567', $profile->mobile);
        $this->assertSame('0300 1234567', $profile->mobile_formatted);
    }

    public function test_a_landline_is_refused_with_an_explanation(): void
    {
        Storage::fake(config('atompay.kyc.disk'));

        $this->actingAs($this->customer())
            ->post(route('account.application.store'), $this->application(['mobile' => '042 35678901']))
            ->assertSessionHasErrors(['mobile' => 'That looks like a landline. Enter a mobile number so we can reach you about payments.']);
    }

    public function test_a_cnic_with_an_impossible_province_code_is_refused(): void
    {
        Storage::fake(config('atompay.kyc.disk'));

        $this->actingAs($this->customer())
            ->post(route('account.application.store'), $this->application(['cnic' => '92101-1234567-1']))
            ->assertSessionHasErrors('cnic');
    }

    public function test_a_short_cnic_says_how_many_digits_were_given(): void
    {
        Storage::fake(config('atompay.kyc.disk'));

        $this->actingAs($this->customer())
            ->post(route('account.application.store'), $this->application(['cnic' => '42101-12345']))
            ->assertSessionHasErrors(['cnic' => 'A CNIC has 13 digits; you entered 10.']);
    }

    /**
     * The area is captured with the address so Atomshop can raise an order
     * without guessing it, and it only means anything inside its own city.
     */
    public function test_an_area_is_saved_with_the_city_it_belongs_to(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('account.application.store'), $this->application([
                'city_id' => $area->city_id,
                'area_id' => $area->id,
            ]))
            ->assertSessionHasNoErrors();

        $profile = KycProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame((int) $area->city_id, (int) $profile->city_id);
        $this->assertSame((int) $area->id, (int) $profile->area_id);
        $this->assertSame($area->title, $profile->area->title);
    }

    public function test_an_area_from_another_city_is_refused(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();
        $elsewhere = Area::query()->where('status', 'active')
            ->where('city_id', '!=', $area->city_id)->whereNotNull('city_id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('account.application.store'), $this->application([
                'city_id' => $area->city_id,
                'area_id' => $elsewhere->id,
            ]))
            ->assertSessionHasErrors(['area_id' => 'Choose an area that is inside the city you selected.']);

        $this->assertDatabaseMissing('atompay_kyc_profiles', ['user_id' => $user->id]);
    }

    /** No city means there is nothing to validate the area against, so it is dropped. */
    public function test_an_area_without_a_city_is_ignored_rather_than_refused(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('account.application.store'), $this->application([
                'city_id' => null,
                'area_id' => $area->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(KycProfile::where('user_id', $user->id)->firstOrFail()->area_id);
    }

    /** The dependent picker's source: one city's areas, and only to a customer. */
    public function test_the_areas_endpoint_lists_one_citys_areas_to_a_signed_in_customer(): void
    {
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();

        $rows = $this->actingAs($this->customer())
            ->getJson(route('account.areas', ['city' => $area->city_id]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($rows);
        $this->assertSame(['id', 'title'], array_keys($rows[0]));
        $this->assertContains($area->id, array_column($rows, 'id'));

        // Every row really is in that city, so the picker cannot offer an
        // area the validation rule would then reject.
        $ids = Area::query()->where('city_id', $area->city_id)->where('status', 'active')->pluck('id')->all();
        foreach (array_column($rows, 'id') as $id) {
            $this->assertContains($id, $ids);
        }

        $this->app['auth']->forgetGuards();
        $this->get(route('account.areas', ['city' => $area->city_id]))->assertRedirect(route('login'));
    }

    /** Long lists are unusable as plain dropdowns - 474 areas in Lahore alone. */
    public function test_every_dropdown_on_the_application_form_is_searchable(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $html = $this->actingAs($this->customer())->get(route('account.application'))
            ->assertOk()->getContent();

        // Every <select> the form renders, by the id the view gives it. Listed
        // rather than scraped so a new dropdown has to be added here too.
        $dropdowns = ['city_id', 'area_id', 'employment_status', 'income_source'];
        $this->assertSame(
            count($dropdowns),
            substr_count($html, '<select'),
            'the form renders a dropdown this test does not know about',
        );

        foreach ($dropdowns as $id) {
            $tag = Str::before(Str::after($html, '<select id="'.$id.'"'), '>');
            $this->assertNotSame('', $tag, "no dropdown rendered for {$id}");
            $this->assertStringContainsString('data-search', $tag, "{$id} is not searchable");
        }

        // The area picker is driven by the city picker, and knows where to get
        // its options; without both it is just an empty dropdown.
        $area = Str::before(Str::after($html, '<select id="area_id"'), '>');
        $this->assertStringContainsString('data-area-for="city_id"', $area);
        $this->assertStringContainsString('/my/areas/__CITY__', $area);
    }

    /**
     * The mobile app and the v1 form predate the area field and send nothing;
     * an update from either must leave the area the customer picked on the web.
     */
    public function test_a_submission_without_an_area_keeps_the_one_on_file(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();

        $this->actingAs($user)->post(route('account.application.store'), $this->application([
            'city_id' => $area->city_id, 'area_id' => $area->id,
        ]))->assertSessionHasNoErrors();

        // Same city, no area key at all - as an older client would post.
        $without = $this->application(['city_id' => $area->city_id, 'monthly_income' => 240000]);
        unset($without['area_id']);

        $this->actingAs($user->fresh())->post(route('account.application.store'), $without)
            ->assertSessionHasNoErrors();

        $this->assertSame((int) $area->id, (int) KycProfile::where('user_id', $user->id)->firstOrFail()->area_id);
    }

    /** But a city change drops an area that city cannot contain. */
    public function test_changing_city_without_an_area_clears_the_stale_one(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $area = Area::query()->where('status', 'active')->whereNotNull('city_id')->firstOrFail();
        $otherCity = Area::query()->where('status', 'active')
            ->where('city_id', '!=', $area->city_id)->whereNotNull('city_id')->firstOrFail()->city_id;

        $this->actingAs($user)->post(route('account.application.store'), $this->application([
            'city_id' => $area->city_id, 'area_id' => $area->id,
        ]))->assertSessionHasNoErrors();


        $moved = $this->application(['city_id' => $otherCity]);
        unset($moved['area_id']);

        $this->actingAs($user)->post(route('account.application.store'), $moved)
            ->assertSessionHasNoErrors();

        $profile = KycProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame((int) $otherCity, (int) $profile->city_id);
        $this->assertNull($profile->area_id, 'an area from the previous city must not survive the move');
    }
}
