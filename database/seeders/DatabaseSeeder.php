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
