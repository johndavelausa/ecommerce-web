<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SellerActivityLog;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Console\Command;

class AutoCancelUnacceptedOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-cancel-unaccepted
        {--accept-hours=12 : Auto-cancel orders still in paid status after this many hours}
        {--penalty-points=1 : Penalty points attached to each seller SLA miss}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel paid orders that were not accepted by seller within the SLA window.';

    public function handle(): int
    {
        $acceptHours = max(1, (int) $this->option('accept-hours'));
        $penaltyPoints = max(1, (int) $this->option('penalty-points'));
        $cutoff = now()->subHours($acceptHours);

        $cancelledCount = 0;

        Order::query()
            ->with('customer')
            ->where('status', Order::STATUS_PAID)
            ->where('created_at', '<=', $cutoff)
            ->chunkById(200, function ($orders) use ($acceptHours, $penaltyPoints, &$cancelledCount) {
                /** @var \Illuminate\Support\Collection<int, Order|mixed> $orders */
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    if (! $order->canTransitionTo(Order::STATUS_CANCELLED, 'system')) {
                        continue;
                    }

                    $fromStatus = (string) $order->status;

                    $order->status = Order::STATUS_CANCELLED;
                    $order->cancelled_at = $order->freshTimestamp();
                    $order->cancelled_by_type = 'system';
                    $order->cancellation_reason_code = 'seller_acceptance_sla_missed';
                    $order->cancellation_reason_note = sprintf('Seller did not accept order within %d hour(s).', $acceptHours);
                    $order->applyCancellationRefundDecision($fromStatus);
                    $order->save();

                    if ($order->seller_id) {
                        SellerActivityLog::log((int) $order->seller_id, 'order_auto_cancelled_unaccepted', [
                            'order_id' => $order->id,
                            'accept_hours' => $acceptHours,
                            'penalty_points' => $penaltyPoints,
                            'penalty_code' => 'acceptance_sla_miss',
                        ]);
                    }

                    $customer = $order->customer;
                    if ($customer) {
                        $customer->notify(new OrderStatusUpdated($order));
                    }

                    $cancelledCount++;
                }
            });

        $this->info("Auto-cancelled {$cancelledCount} unaccepted order(s). SLA: {$acceptHours}h. Penalty points: {$penaltyPoints}.");

        return self::SUCCESS;
    }
}
