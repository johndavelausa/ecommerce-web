<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderTrackingEvent;
use App\Models\User;
use App\Notifications\OrderSlaAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WatchStaleShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:watch-stale-shipments
        {--in-transit-hours=72 : Alert when shipped/in-transit orders have no fresh tracking activity beyond this threshold}
        {--out-for-delivery-hours=24 : Alert when out-for-delivery orders remain unresolved beyond this threshold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send stale shipment watchdog alerts for delayed in-transit and out-for-delivery orders.';

    public function handle(): int
    {
        $inTransitHours = max(1, (int) $this->option('in-transit-hours'));
        $outForDeliveryHours = max(1, (int) $this->option('out-for-delivery-hours'));

        $inTransitCutoff = now()->subHours($inTransitHours);
        $outForDeliveryCutoff = now()->subHours($outForDeliveryHours);

        $admins = User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get();

        $sellerReminderCount = 0;
        $adminAlertCount = 0;

        Order::query()
            ->with(['seller.user', 'trackingEvents'])
            ->where('status', Order::STATUS_SHIPPED)
            ->where(function ($q) use ($inTransitCutoff) {
                $q->whereNotNull('shipped_at')->where('shipped_at', '<=', $inTransitCutoff)
                    ->orWhere('updated_at', '<=', $inTransitCutoff);
            })
            ->chunkById(200, function ($orders) use ($inTransitHours, $inTransitCutoff, $admins, &$sellerReminderCount, &$adminAlertCount) {
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    $lastTrackingAt = $this->resolveLastTrackingActivity($order);
                    if ($lastTrackingAt && $lastTrackingAt->greaterThan($inTransitCutoff)) {
                        continue;
                    }

                    $sellerUser = $order->seller?->user;
                    if ($sellerUser && $this->canSendAlert($order->id, 'stale_in_transit', 'seller_reminder')) {
                        $sellerUser->notify(new OrderSlaAlert($order, 'stale_in_transit', 'seller_reminder', $inTransitHours));
                        $sellerReminderCount++;
                    }

                    if ($this->canSendAlert($order->id, 'stale_in_transit', 'admin_anomaly')) {
                        foreach ($admins as $admin) {
                            $admin->notify(new OrderSlaAlert($order, 'stale_in_transit', 'admin_anomaly', $inTransitHours));
                            $adminAlertCount++;
                        }
                    }
                }
            });

        Order::query()
            ->with(['seller.user', 'trackingEvents'])
            ->where('status', Order::STATUS_OUT_FOR_DELIVERY)
            ->where('updated_at', '<=', $outForDeliveryCutoff)
            ->chunkById(200, function ($orders) use ($outForDeliveryHours, $outForDeliveryCutoff, $admins, &$sellerReminderCount, &$adminAlertCount) {
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    $lastTrackingAt = $this->resolveLastTrackingActivity($order);
                    if ($lastTrackingAt && $lastTrackingAt->greaterThan($outForDeliveryCutoff)) {
                        continue;
                    }

                    $sellerUser = $order->seller?->user;
                    if ($sellerUser && $this->canSendAlert($order->id, 'stale_out_for_delivery', 'seller_reminder')) {
                        $sellerUser->notify(new OrderSlaAlert($order, 'stale_out_for_delivery', 'seller_reminder', $outForDeliveryHours));
                        $sellerReminderCount++;
                    }

                    if ($this->canSendAlert($order->id, 'stale_out_for_delivery', 'admin_anomaly')) {
                        foreach ($admins as $admin) {
                            $admin->notify(new OrderSlaAlert($order, 'stale_out_for_delivery', 'admin_anomaly', $outForDeliveryHours));
                            $adminAlertCount++;
                        }
                    }
                }
            });

        $this->info("Stale shipment watchdog complete. Seller reminders: {$sellerReminderCount}; Admin alerts: {$adminAlertCount}.");

        return self::SUCCESS;
    }

    protected function resolveLastTrackingActivity(Order $order): ?Carbon
    {
        $latestEvent = $order->trackingEvents->first();
        if ($latestEvent instanceof OrderTrackingEvent) {
            $occurredAt = $latestEvent->occurred_at;
            if ($occurredAt instanceof Carbon) {
                return $occurredAt;
            }
        }

        if ($order->shipped_at instanceof Carbon) {
            return $order->shipped_at;
        }

        return $order->updated_at;
    }

    protected function canSendAlert(int $orderId, string $stage, string $alertType): bool
    {
        $key = sprintf('orders:watchdog:%s:%s:%d:%s', $stage, $alertType, $orderId, now()->format('Ymd'));

        return Cache::add($key, true, now()->endOfDay());
    }
}
