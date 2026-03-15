<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SellerPayout;
use App\Notifications\SellerPayoutReleased;
use Illuminate\Console\Command;

class ReleaseSellerPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:release-payouts {--hours=0 : Minimum order completion age in hours before payout release}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release seller payouts for completed orders after dispute closure checks.';

    public function handle(): int
    {
        $minHours = max(0, (int) $this->option('hours'));
        $cutoff = now()->subHours($minHours);

        $releasedCount = 0;
        $holdCount = 0;

        Order::query()
            ->with(['seller.user', 'disputes', 'payout'])
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->chunkById(200, function ($orders) use (&$releasedCount, &$holdCount) {
                /** @var \Illuminate\Support\Collection<int, Order|mixed> $orders */
                foreach ($orders as $order) {
                    if (! $order instanceof Order) {
                        continue;
                    }

                    $disputes = $order->disputes;
                    $decision = SellerPayout::decisionFromDisputes(collect($disputes));

                    $gross = (float) $order->total_amount;
                    $platformFeeRate = 0.1000;
                    $platformFeeAmount = round($gross * $platformFeeRate, 2);
                    $net = round(max(0, $gross - $platformFeeAmount), 2);

                    $payout = $order->payout;
                    if (! $payout) {
                        $payout = SellerPayout::create([
                            'seller_id' => $order->seller_id,
                            'order_id' => $order->id,
                            'gross_amount' => $gross,
                            'platform_fee_rate' => $platformFeeRate,
                            'platform_fee_amount' => $platformFeeAmount,
                            'net_amount' => $net,
                            'status' => $decision['status'],
                            'hold_reason' => $decision['hold_reason'],
                            'released_at' => $decision['status'] === SellerPayout::STATUS_RELEASED ? now() : null,
                        ]);

                        if ($decision['status'] === SellerPayout::STATUS_RELEASED) {
                            $order->seller?->user?->notify(new SellerPayoutReleased($payout));
                            $releasedCount++;
                        } else {
                            $holdCount++;
                        }

                        continue;
                    }

                    if (
                        $payout->status === SellerPayout::STATUS_ON_HOLD
                        && SellerPayout::isManualHoldReason($payout->hold_reason)
                        && $decision['status'] === SellerPayout::STATUS_RELEASED
                    ) {
                        continue;
                    }

                    $previousStatus = $payout->status;
                    $payout->status = $decision['status'];
                    $payout->hold_reason = $decision['status'] === SellerPayout::STATUS_ON_HOLD
                        ? $decision['hold_reason']
                        : null;
                    $payout->released_at = $decision['status'] === SellerPayout::STATUS_RELEASED ? now() : null;
                    $payout->save();

                    if ($previousStatus !== SellerPayout::STATUS_RELEASED && $payout->status === SellerPayout::STATUS_RELEASED) {
                        $order->seller?->user?->notify(new SellerPayoutReleased($payout));
                    }

                    if ($payout->status === SellerPayout::STATUS_RELEASED) {
                        $releasedCount++;
                    } else {
                        $holdCount++;
                    }
                }
            });

        $this->info("Payout release complete. Released: {$releasedCount}; On hold: {$holdCount}; Cutoff: {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
