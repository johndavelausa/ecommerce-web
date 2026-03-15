<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAction extends Model
{
    protected $fillable = ['admin_id', 'action', 'target_type', 'target_id', 'reason', 'details'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function logOrderStatusOverride(int $orderId, string $oldStatus, string $newStatus, string $reason, ?int $adminId = null): void
    {
        static::query()->create([
            'admin_id' => $adminId ?? auth('admin')->id(),
            'action' => 'order_status_override',
            'target_type' => 'order',
            'target_id' => $orderId,
            'reason' => $reason,
            'details' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        ]);
    }
}
