<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderSlaAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MonitorOrderSla extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:monitor-sla
        {--accept-hours=12 : Pre-shipping orders older than this trigger seller acceptance reminders}
        {--ship-hours=48 : Shipped orders older than this trigger shipping anomaly alerts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send seller SLA reminders and admin anomaly alerts for delayed orders.';

    public function handle(): int
    {
        $acceptHours = max(1, (int) $this->option('accept-hours'));
        $shipHours = max(1, (int) $this->option('ship-hours'));

        $acceptCutoff = now()->subHours($acceptHours);
        $shipCutoff = now()->subHours($shipHours);

        $admins = User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get();

        $sellerReminderCount = 0;
        $adminAlertCount = 0;

        Order::query()
            ->with('seller.user')
            ->where('status', Order::STATUS_PAID)
            ->where('created_at', '<=', $acceptCutoff)
            ->chunkById(200, function ($orders) use ($acceptHours, $admins, &$sellerReminderCount, &$adminAlertCount) {
                /** @var \Illuminate\Support\Collection<int, Order|mixed> $orders */
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    $sellerUser = $order->seller?->user;
                    if ($sellerUser && $this->canSendAlert($order->id, 'acceptance', 'seller_reminder')) {
                        $sellerUser->notify(new OrderSlaAlert($order, 'acceptance', 'seller_reminder', $acceptHours));
                        $sellerReminderCount++;
                    }

                    if ($this->canSendAlert($order->id, 'acceptance', 'admin_anomaly')) {
                        foreach ($admins as $admin) {
                            $admin->notify(new OrderSlaAlert($order, 'acceptance', 'admin_anomaly', $acceptHours));
                            $adminAlertCount++;
                        }
                    }
                }
            });

        Order::query()
            ->with('seller.user')
            ->where('status', Order::STATUS_SHIPPED)
            ->whereNotNull('shipped_at')
            ->where('shipped_at', '<=', $shipCutoff)
            ->chunkById(200, function ($orders) use ($shipHours, $admins, &$sellerReminderCount, &$adminAlertCount) {
                /** @var \Illuminate\Support\Collection<int, Order|mixed> $orders */
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    $sellerUser = $order->seller?->user;
                    if ($sellerUser && $this->canSendAlert($order->id, 'shipping', 'seller_reminder')) {
                        $sellerUser->notify(new OrderSlaAlert($order, 'shipping', 'seller_reminder', $shipHours));
                        $sellerReminderCount++;
                    }

                    if ($this->canSendAlert($order->id, 'shipping', 'admin_anomaly')) {
                        foreach ($admins as $admin) {
                            $admin->notify(new OrderSlaAlert($order, 'shipping', 'admin_anomaly', $shipHours));
                            $adminAlertCount++;
                        }
                    }
                }
            });

        $this->info("SLA monitor complete. Seller reminders: {$sellerReminderCount}; Admin alerts: {$adminAlertCount}.");

        return self::SUCCESS;
    }

    protected function canSendAlert(int $orderId, string $stage, string $alertType): bool
    {
        $key = sprintf('orders:sla:%s:%s:%d:%s', $stage, $alertType, $orderId, now()->format('Ymd'));

        return Cache::add($key, true, now()->endOfDay());
    }
}
