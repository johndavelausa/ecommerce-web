<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily subscription status updater (grace period / lapsed logic)
Schedule::command('subscriptions:update-status')->daily();

// Order lifecycle automation: complete delivered orders after dispute window.
Schedule::command('orders:auto-complete-delivered --days=3')->hourly();

// SLA monitoring: seller reminders + admin anomaly alerts for delayed orders.
Schedule::command('orders:monitor-sla --accept-hours=12 --ship-hours=48')->everyThirtyMinutes();

// Seller acceptance SLA enforcement: auto-cancel stale paid orders and log penalty metadata.
Schedule::command('orders:auto-cancel-unaccepted --accept-hours=12 --penalty-points=1')->everyThirtyMinutes();

// Stale shipment watchdog: alerts for delayed in-transit and out-for-delivery orders.
Schedule::command('orders:watch-stale-shipments --in-transit-hours=72 --out-for-delivery-hours=24')->everyThirtyMinutes();

// Payout automation: release eligible seller payouts after completion/dispute checks.
Schedule::command('orders:release-payouts --hours=0')->hourly();
