<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The company facts Atomshop's admin panel keeps in the shared
 * `atom_settings` table (key `atompay.<name>`): who sells on instalments,
 * where, where the data is hosted, and the late-payment, early-settlement
 * and complaint terms.
 *
 * A key the admin left empty (or blank) falls back to config('atompay.company'),
 * which reads .env; anything still unset is null - never a guess.
 *
 * Atomshop has its own cache, so it cannot clear this one when the admin
 * saves; the TTL is kept short so a change shows within a minute.
 */
class CompanySettings
{
    /** Every setting, in the order GET /app-config lists them. */
    public const KEYS = [
        'company_name',
        'registered_address',
        'data_hosting',
        'late_payment',
        'early_settlement',
        'complaint_response_time',
    ];

    public const TABLE = 'atom_settings';

    public const PREFIX = 'atompay.';

    public const CACHE_KEY = 'atompay.company_settings';

    public const CACHE_TTL = 60;   // seconds

    /** @return array{company_name: ?string, registered_address: ?string, data_hosting: ?string, late_payment: ?string, early_settlement: ?string, complaint_response_time: ?string} */
    public function all(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->stored());

        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = self::clean($stored[$key] ?? null) ?? self::clean(config("atompay.company.{$key}"));
        }

        return $settings;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /** Re-read the table on the next call (tests, or after a known change). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What the admin has saved, by short key. A missing table (Atomshop not
     * migrated yet, a fresh test database) means nothing saved, not an error.
     *
     * @return array<string, ?string>
     */
    private function stored(): array
    {
        try {
            return DB::table(self::TABLE)
                ->whereIn('key', array_map(fn (string $key) => self::PREFIX.$key, self::KEYS))
                ->pluck('value', 'key')
                ->mapWithKeys(fn ($value, string $key) => [substr($key, strlen(self::PREFIX)) => $value])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /** Empty or whitespace-only is "not set". */
    private static function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
