<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-reset wewenang absen berkala dan tepat pada pergantian jam shift
Schedule::command('absen:auto-reset')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('14:00')
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('18:00')
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('19:00')
    ->withoutOverlapping();

Schedule::command('absen:auto-reset')
    ->dailyAt('22:00')
    ->withoutOverlapping();
