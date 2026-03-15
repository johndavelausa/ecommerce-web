<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerPayout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RELEASED = 'released';
    public const STATUS_ON_HOLD = 'on_hold';

    public const HOLD_REASON_DISPUTE_ACTIVE = 'dispute_active';
    public const HOLD_REASON_DISPUTE_REFUND_PENDING = 'dispute_refund_pending';
    public const HOLD_REASON_DISPUTE_REFUND_COMPLETED = 'dispute_refund_completed';

    public const SYSTEM_DISPUTE_HOLD_REASONS = [
        self::HOLD_REASON_DISPUTE_ACTIVE,
        self::HOLD_REASON_DISPUTE_REFUND_PENDING,
        self::HOLD_REASON_DISPUTE_REFUND_COMPLETED,
    ];

    protected $fillable = [
        'seller_id',
        'order_id',
        'gross_amount',
        'platform_fee_rate',
        'platform_fee_amount',
        'net_amount',
        'status',
        'hold_reason',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_rate' => 'decimal:4',
            'platform_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public static function decisionFromDisputes(iterable $disputes): array
    {
        $statuses = collect($disputes)->pluck('status')->map(fn ($s) => (string) $s);

        if ($statuses->contains(fn ($status) => in_array($status, OrderDispute::ACTIVE_STATUSES, true))) {
            return [
                'status' => self::STATUS_ON_HOLD,
                'hold_reason' => self::HOLD_REASON_DISPUTE_ACTIVE,
            ];
        }

        if ($statuses->contains(OrderDispute::STATUS_REFUND_COMPLETED)) {
            return [
                'status' => self::STATUS_ON_HOLD,
                'hold_reason' => self::HOLD_REASON_DISPUTE_REFUND_COMPLETED,
            ];
        }

        if ($statuses->contains(fn ($status) => in_array($status, [
            OrderDispute::STATUS_REFUND_PENDING,
            OrderDispute::STATUS_RESOLVED_APPROVED,
        ], true))) {
            return [
                'status' => self::STATUS_ON_HOLD,
                'hold_reason' => self::HOLD_REASON_DISPUTE_REFUND_PENDING,
            ];
        }

        return [
            'status' => self::STATUS_RELEASED,
            'hold_reason' => null,
        ];
    }

    public static function isManualHoldReason(?string $holdReason): bool
    {
        if ($holdReason === null || $holdReason === '') {
            return false;
        }

        return ! in_array($holdReason, self::SYSTEM_DISPUTE_HOLD_REASONS, true);
    }
}
