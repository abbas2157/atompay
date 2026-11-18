<?php

namespace Tests\Feature;

use App\Services\CompanySettings;
use App\Services\CreditAssessmentService;
use App\Services\InstalmentQuoteService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The AtomPay numbers (processing fee %, credit ratios) follow the atom_settings rows AtomShop shares, so the
 * two apps can't disagree (docs/trd/2026-11-18-atompay-shared-numbers.md, AtomShop side).
 *
 * Writes to atom_settings and removes its own rows afterwards.
 */
class SharedNumbersTest extends TestCase
{
    /** @var array<string, ?string> */
    private array $before = [];

    private const KEYS = ['atompay.processing_fee_percent', 'atompay.limit_ratio', 'atompay.instalment_ratio'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::KEYS as $key) {
            $this->before[$key] = rescue(fn () => DB::table('atom_settings')->where('key', $key)->value('value'), null, false);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->before as $key => $value) {
            if ($value === null) {
                DB::table('atom_settings')->where('key', $key)->delete();
            } else {
                DB::table('atom_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
            }
        }
        CompanySettings::flush();
        parent::tearDown();
    }

    private function set(string $key, string $value): void
    {
        DB::table('atom_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        CompanySettings::flush();
    }

    public function test_the_plan_fee_follows_the_shared_row(): void
    {
        $this->set('atompay.processing_fee_percent', '5');

        $plan = app(InstalmentQuoteService::class)->atompayPlan(100000);

        $this->assertSame(5.0, $plan['fee_percent']);
        $this->assertSame(5000, $plan['processing_fee']);
    }

    public function test_the_credit_limit_follows_the_shared_ratios(): void
    {
        $this->set('atompay.limit_ratio', '0.40');
        $this->set('atompay.instalment_ratio', '0.20');

        $estimate = app(CreditAssessmentService::class)->estimate(100000);

        $this->assertSame(40000, $estimate['approved_limit']);
        $this->assertSame(20000, $estimate['max_instalment']);
    }

    public function test_a_missing_row_falls_back_to_the_config_default(): void
    {
        foreach (self::KEYS as $key) {
            DB::table('atom_settings')->where('key', $key)->delete();
        }
        CompanySettings::flush();

        $settings = app(CompanySettings::class);
        $this->assertSame((float) config('atompay.atompay_plan.processing_fee_percent'), $settings->number('processing_fee_percent', (float) config('atompay.atompay_plan.processing_fee_percent')));
        $this->assertSame((float) config('atompay.credit.limit_ratio'), $settings->number('limit_ratio', (float) config('atompay.credit.limit_ratio')));
    }
}
