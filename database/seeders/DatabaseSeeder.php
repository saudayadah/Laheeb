<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ProductSeeder::class,
            ExpenseCategorySeeder::class,
        ]);

        // Never ship a known password to production: seeding there requires
        // an explicit SEED_OWNER_PASSWORD, and the demo address is skipped
        // entirely once a real owner exists.
        if (app()->isProduction()) {
            $password = env('SEED_OWNER_PASSWORD');

            if ($password === null || $password === '') {
                if (User::role('owner')->exists()) {
                    return; // owners already set up — nothing to seed
                }

                throw new \RuntimeException('Set SEED_OWNER_PASSWORD in .env before seeding production.');
            }

            $owner = User::firstOrCreate(
                ['email' => 'owner@laheeb.test'],
                ['name' => 'المدير العام', 'password' => $password],
            );
            $owner->syncRoles(['owner']);

            return;
        }

        $owner = User::firstOrCreate(
            ['email' => 'owner@laheeb.test'],
            ['name' => 'المدير العام', 'password' => 'password'],
        );
        $owner->syncRoles(['owner']);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
