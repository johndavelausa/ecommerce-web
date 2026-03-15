<?php

namespace App\Console\Commands;

use App\Models\OrderDispute;
use App\Models\User;
use App\Notifications\OrderDisputeUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EscalateOrderDisputes extends Command
{
    protected $signature = 'orders:escalate-disputes
        {--seller-hours=24 : Escalate open disputes to admin review after this many hours without seller response}
        {--admin-hours=24 : Re-alert admins for disputes waiting in seller_review beyond this many hours}';

    protected $description = 'Escalate stale order disputes and notify admins based on SLA windows.';

    public function handle(): int
    {
        $sellerHours = max(1, (int) $this->option('seller-hours'));
        $adminHours = max(1, (int) $this->option('admin-hours'));

        $sellerCutoff = now()->subHours($sellerHours);
        $adminCutoff = now()->subHours($adminHours);

        $admins = User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get();

        $escalated = 0;
        $adminAlerts = 0;

        OrderDispute::query()
            ->with(['customer', 'seller.user'])
            ->where('status', OrderDispute::STATUS_OPEN)
            ->where('created_at', '<=', $sellerCutoff)
            ->chunkById(200, function ($disputes) use (&$escalated, &$adminAlerts, $admins) {
                foreach ($disputes as $dispute) {
                    if (! $dispute instanceof OrderDispute) {
                        continue;
                    }

                    $dispute->status = OrderDispute::STATUS_UNDER_ADMIN_REVIEW;
                    $dispute->admin_resolution_note = trim((string) ($dispute->admin_resolution_note ?? ''));
                    if ($dispute->admin_resolution_note === '') {
                        $dispute->admin_resolution_note = 'Auto-escalated to admin review due to seller-response SLA breach.';
                    }
                    $dispute->save();

                    $this->notifyParties($dispute, 'auto_escalated_seller_sla', $admins, $adminAlerts);
                    $escalated++;
                }
            });

        OrderDispute::query()
            ->with(['customer', 'seller.user'])
            ->where('status', OrderDispute::STATUS_SELLER_REVIEW)
            ->whereNotNull('seller_responded_at')
            ->where('seller_responded_at', '<=', $adminCutoff)
            ->chunkById(200, function ($disputes) use (&$adminAlerts, $admins) {
                foreach ($disputes as $dispute) {
                    if (! $dispute instanceof OrderDispute) {
                        continue;
                    }

                    // Move to admin review if not already there and notify admins for action.
                    $dispute->status = OrderDispute::STATUS_UNDER_ADMIN_REVIEW;
                    $dispute->save();

                    $this->notifyParties($dispute, 'auto_escalated_admin_sla', $admins, $adminAlerts);
                }
            });

        $this->info("Dispute escalation complete. Escalated: {$escalated}; admin alerts: {$adminAlerts}.");

        return self::SUCCESS;
    }

    protected function notifyParties(OrderDispute $dispute, string $event, $admins, int &$adminAlerts): void
    {
        if ($dispute->customer) {
            $dispute->customer->notify(new OrderDisputeUpdated($dispute, $event));
        }

        $sellerUser = $dispute->seller?->user;
        if ($sellerUser) {
            $sellerUser->notify(new OrderDisputeUpdated($dispute, $event));
        }

        foreach ($admins as $admin) {
            if ($this->canSendAdminAlert($dispute->id, $event, $admin->id)) {
                $admin->notify(new OrderDisputeUpdated($dispute, $event));
                $adminAlerts++;
            }
        }
    }

    protected function canSendAdminAlert(int $disputeId, string $event, int $adminId): bool
    {
        $key = sprintf('orders:dispute-escalation:%s:%d:%d:%s', $event, $disputeId, $adminId, now()->format('Ymd'));

        return Cache::add($key, true, now()->endOfDay());
    }
}
