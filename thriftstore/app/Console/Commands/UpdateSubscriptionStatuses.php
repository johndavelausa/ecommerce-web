<?php

namespace App\Console\Commands;

use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateSubscriptionStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update seller subscription_status based on subscription_due_date and close stores when lapsed.';

    public function handle(): int
    {
        $today = Carbon::today();

        Seller::query()
            ->whereNotNull('subscription_due_date')
            ->chunkById(200, function ($sellers) use ($today) {
                foreach ($sellers as $seller) {
                    $due = Carbon::parse($seller->subscription_due_date)->startOfDay();

                    if ($today->lessThanOrEqualTo($due)) {
                        $status = 'active';
                    } elseif ($today->lessThanOrEqualTo($due->copy()->addDays(7))) {
                        $status = 'grace_period';
                    } else {
                        $status = 'lapsed';
                    }

                    $updates = ['subscription_status' => $status];

                    // If subscription has fully lapsed, close the store (but do not change admin approval status).
                    if ($status === 'lapsed' && $seller->is_open) {
                        $updates['is_open'] = false;
                    }

                    $seller->update($updates);
                }
            });

        $this->info('Subscription statuses updated.');

        return self::SUCCESS;
    }
}

