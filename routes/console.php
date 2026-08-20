<?php

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:run', function (BackupService $backup): int {
    $path = $backup->create();
    $this->info('Backup created on private disk: '.$path);

    return self::SUCCESS;
})->purpose('Create a private backup archive for database/private storage');

Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
