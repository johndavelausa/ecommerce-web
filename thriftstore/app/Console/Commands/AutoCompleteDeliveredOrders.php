<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderDispute;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;

class AutoCompleteDeliveredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete-delivered {--days=3 : Number of days after delivered_at before auto-complete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete delivered orders after the dispute window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $completedCount = 0;

        Order::query()
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->whereDoesntHave('disputes', function ($q) {
                $q->whereIn('status', OrderDispute::ACTIVE_STATUSES);
            })
            ->chunkById(200, function ($orders) use (&$completedCount) {
                /** @var \Illuminate\Support\Collection<int, Order> $orders */
                foreach ($orders as $order) {
                    /** @var Order $order */
                    if (! $order->canTransitionTo(Order::STATUS_COMPLETED, 'system')) {
                        continue;
                    }

                    $order->status = Order::STATUS_COMPLETED;
                    $order->completed_at = Carbon::now();
                    $order->save();

                    $customer = $order->customer;
                    if ($customer) {
                        $customer->notify(new OrderStatusUpdated($order));
                    }

                    $completedCount++;
                }
            });

        $this->info("Auto-completed {$completedCount} order(s) with delivered_at on or before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
