<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('atompay.kyc.disk'));
    }

    private function identity(array $overrides = []): array
    {
        return [
            'full_name' => 'Ayesha Khan',
            'cnic' => '42101-7654321-9',
            'mobile' => '+92 399 5556666',
            'date_of_birth' => '1992-03-14',
            'residential_address' => 'House 7, Street 3, Gulshan, Karachi',
            'cnic_front' => UploadedFile::fake()->image('front.jpg'),
            'cnic_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            ...$overrides,
        ];
    }

    public function test_an_unsubmitted_profile_is_prefilled_from_atomshop(): void
    {
        $user = $this->makeCustomer(['name' => 'Ayesha Khan', 'phone' => '03995556666']);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.status', 'not_started')
            ->assertJsonPath('data.full_name', 'Ayesha Khan')
            ->assertJsonPath('data.mobile', '03995556666')
            ->assertJsonPath('data.documents.selfie', ['uploaded' => false, 'url' => null]);
    }

    public function test_a_customer_submits_their_identity_and_documents(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->post('/api/v1/profile', $this->identity(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.cnic', '4210176543219')
            ->assertJsonPath('data.cnic_formatted', '42101-7654321-9')
            ->assertJsonPath('data.mobile', '03995556666')
            ->assertJsonPath('data.date_of_birth', '1992-03-14')
            ->assertJsonPath('data.documents.cnic_front.uploaded', true)
            ->assertJsonPath('data.documents.cnic_front.url', route('api.v1.profile.documents', 'cnic_front'));

        $profile = KycProfile::where('user_id', $user->id)->sole();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        Storage::disk(config('atompay.kyc.disk'))->assertExists($profile->selfie_path);

        // Updating later without re-uploading keeps the documents on file.
        $this->freshRequest()->withToken($token)
            ->post('/api/v1/profile', $this->identity(['cnic_front' => null, 'cnic_back' => null, 'selfie' => null, 'full_name' => 'Ayesha K. Khan']), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Ayesha K. Khan')
            ->assertJsonPath('data.documents.selfie.uploaded', true);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/me')->assertJsonPath('data.kyc_status', 'pending');
    }

    public function test_the_first_submission_needs_all_three_documents(): void
    {
        $user = $this->makeCustomer();

        $this->withToken($this->tokenFor($user))
            ->post('/api/v1/profile', $this->identity(['selfie' => null, 'cnic' => '1234', 'date_of_birth' => now()->subYears(10)->toDateString()]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selfie', 'cnic', 'date_of_birth']);
    }

    public function test_a_customer_can_view_only_their_own_documents(): void
    {
        $owner = $this->makeCustomer();
        $this->withToken($this->tokenFor($owner))->post('/api/v1/profile', $this->identity(), ['Accept' => 'application/json'])->assertCreated();

        $this->freshRequest()->withToken($this->tokenFor($owner))->get('/api/v1/profile/documents/selfie')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        // Another customer asking for "selfie" gets their own - which does not exist.
        $other = $this->makeCustomer();
        $this->freshRequest()->withToken($this->tokenFor($other))->getJson('/api/v1/profile/documents/selfie')->assertNotFound();

        // Only the three customer documents are routable.
        $this->freshRequest()->withToken($this->tokenFor($owner))->getJson('/api/v1/profile/documents/form')->assertNotFound();
    }

    public function test_cities_are_listed_for_the_picker(): void
    {
        $user = $this->makeCustomer();
        $city = City::query()->where('status', 'active')->orderBy('title')->first();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/v1/cities')->assertOk();

        if ($city) {
            $response->assertJsonPath('data.0', ['id' => $city->id, 'name' => $city->title]);
        }
    }
}
