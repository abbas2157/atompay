<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A known customer account for trying AtomPay locally.
 * Writes to AtomShop's shared `users` table, so it refuses to run in production.
 *
 *   php artisan db:seed --class=DemoCustomerSeeder
 */
class DemoCustomerSeeder extends Seeder
{
    public const EMAIL    = 'demo.customer@atompay.test';
    public const PHONE    = '03219876540';
    public const PASSWORD = 'AtomPay@123';

    public function run(): void
    {
        abort_if(app()->isProduction(), 403, 'Demo seeder must not run in production.');

        $user = User::withTrashed()->firstWhere('email', self::EMAIL) ?? new User;

        $user->forceFill([
            'uuid'           => $user->uuid ?? (string) Str::uuid(),
            'name'           => 'Demo Customer',
            'email'          => self::EMAIL,
            'phone'          => self::PHONE,
            'password'       => self::PASSWORD,
            'role'           => config('atompay.customer_role'),
            'status'         => 'active',
            'joined_through' => 'Website',
            'deleted_at'     => null,
        ])->save();

        $this->command?->table(
            ['Field', 'Value'],
            [
                ['User ID', $user->id],
                ['Name', $user->name],
                ['Phone', self::PHONE],
                ['Email', self::EMAIL],
                ['Password', self::PASSWORD],
                ['Role / status', "{$user->role} / {$user->status}"],
            ],
        );
    }
}
