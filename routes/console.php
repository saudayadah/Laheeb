<?php

use App\Services\ExpenseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('expenses:generate-recurring', function (ExpenseService $service) {
    $count = $service->generateRecurring();
    $this->info("Generated {$count} recurring expense drafts.");
})->purpose('Create this month\'s draft expenses from the recurring definitions');

// cPanel: a single cron entry runs `php artisan schedule:run` every minute.
Schedule::command('expenses:generate-recurring')->dailyAt('05:00');
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Nightly database backup with retention, plus a weekly books check.
Schedule::command('backup:clean')->dailyAt('02:45');
Schedule::command('backup:run --only-db')->dailyAt('03:00');
Schedule::command('ledger:verify')->weeklyOn(6, '04:00'); // Saturday
