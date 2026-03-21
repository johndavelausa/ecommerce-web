<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_TO_PACK = 'to_pack';
    public const STATUS_READY_TO_SHIP = 'ready_to_ship';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const REFUND_STATUS_NOT_REQUIRED = 'not_required';
    public const REFUND_STATUS_PENDING = 'pending';
    public const REFUND_STATUS_COMPLETED = 'completed';

    public const REFUND_REASON_PAYMENT_NOT_CAPTURED = 'payment_not_captured';
    public const REFUND_REASON_ORDER_CANCELLED_AFTER_PAYMENT = 'order_cancelled_after_payment';
    public const REFUND_REASON_DISPUTE_APPROVED = 'dispute_refund_approved';
    public const REFUND_REASON_DISPUTE_COMPLETED = 'dispute_refund_completed';
    public const REFUND_REASON_DISPUTE_REJECTED = 'dispute_refund_rejected';

    public const REFUND_STATUSES = [
        self::REFUND_STATUS_NOT_REQUIRED,
        self::REFUND_STATUS_PENDING,
        self::REFUND_STATUS_COMPLETED,
    ];

    public const REFUND_REASONS = [
        self::REFUND_REASON_PAYMENT_NOT_CAPTURED => 'Payment not captured',
        self::REFUND_REASON_ORDER_CANCELLED_AFTER_PAYMENT => 'Order cancelled after payment state',
        self::REFUND_REASON_DISPUTE_APPROVED => 'Dispute approved, refund pending',
        self::REFUND_REASON_DISPUTE_COMPLETED => 'Refund completed after dispute',
        self::REFUND_REASON_DISPUTE_REJECTED => 'Dispute rejected, refund not required',
    ];

    public const STATUSES = [
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_TO_PACK,
        self::STATUS_READY_TO_SHIP,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const COURIERS = [
        'jnt' => 'J&T Express',
        'lbc' => 'LBC Express',
        'flash' => 'Flash Express',
        'ninjavan' => 'Ninja Van',
        'xpost' => 'XPost Courier',
        'other' => 'Other Courier',
    ];

    public const CANCELLATION_REASONS = [
        'buyer_changed_mind' => 'Buyer changed mind',
        'out_of_stock' => 'Out of stock',
        'failed_pickup' => 'Failed pickup',
        'payment_timeout' => 'Payment timeout',
        'fraud_risk' => 'Fraud risk',
        'seller_acceptance_sla_missed' => 'Seller missed acceptance SLA',
    ];

    protected $fillable = [
        'customer_id',
        'seller_id',
        'courier_name',
        'tracking_number',
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'status',
        'total_amount',
        'shipping_address',
        'customer_note',
        'store_rating',
        'store_review',
        'cancelled_at',
        'cancelled_by_type',
        'cancellation_reason_code',
        'cancellation_reason_note',
        'refund_status',
        'refund_reason_code',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'store_rating' => 'integer',
            'estimated_delivery_date' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function applyCancellationRefundDecision(?string $fromStatus = null): void
    {
        $fromStatus = $fromStatus ?? (string) $this->status;

        if ($fromStatus === self::STATUS_AWAITING_PAYMENT) {
            $this->refund_status = self::REFUND_STATUS_NOT_REQUIRED;
            $this->refund_reason_code = self::REFUND_REASON_PAYMENT_NOT_CAPTURED;
            $this->refunded_at = \Illuminate\Support\Carbon::now();

            return;
        }

        $this->refund_status = self::REFUND_STATUS_PENDING;
        $this->refund_reason_code = self::REFUND_REASON_ORDER_CANCELLED_AFTER_PAYMENT;
        $this->refunded_at = null;
    }

    public function applyDisputeRefundDecision(string $disputeStatus): void
    {
        if (in_array($disputeStatus, [OrderDispute::STATUS_REFUND_PENDING, OrderDispute::STATUS_RESOLVED_APPROVED], true)) {
            $this->refund_status = self::REFUND_STATUS_PENDING;
            $this->refund_reason_code = self::REFUND_REASON_DISPUTE_APPROVED;
            $this->refunded_at = null;

            return;
        }

        if ($disputeStatus === OrderDispute::STATUS_REFUND_COMPLETED) {
            $this->refund_status = self::REFUND_STATUS_COMPLETED;
            $this->refund_reason_code = self::REFUND_REASON_DISPUTE_COMPLETED;
            $this->refunded_at = $this->refunded_at ?: \Illuminate\Support\Carbon::now();

            return;
        }

        if ($disputeStatus === OrderDispute::STATUS_RESOLVED_REJECTED
            && $this->refund_status === self::REFUND_STATUS_PENDING
            && $this->refund_reason_code === self::REFUND_REASON_DISPUTE_APPROVED) {
            $this->refund_status = self::REFUND_STATUS_NOT_REQUIRED;
            $this->refund_reason_code = self::REFUND_REASON_DISPUTE_REJECTED;
            $this->refunded_at = null;
        }
    }

    public static function trackingPrefix(string $courier): string
    {
        return match (strtolower(trim($courier))) {
            'jnt' => 'JNT',
            'lbc' => 'LBC',
            'flash' => 'FLS',
            'ninjavan' => 'NJV',
            'xpost' => 'XPT',
            default => 'THF',
        };
    }

    public static function generateTrackingNumber(string $courier): string
    {
        $prefix = static::trackingPrefix($courier);

        do {
            $candidate = sprintf(
                '%s-%s-%04d',
                $prefix,
                now()->format('ymdHis'),
                random_int(1000, 9999)
            );
        } while (static::query()->where('tracking_number', $candidate)->exists());

        return $candidate;
    }

    public static function refundStatusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Not set';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    public static function refundReasonLabel(?string $reasonCode): string
    {
        if ($reasonCode === null || $reasonCode === '') {
            return 'N/A';
        }

        return self::REFUND_REASONS[$reasonCode] ?? ucwords(str_replace('_', ' ', $reasonCode));
    }

    /**
     * Central transition policy for current order lifecycle.
     * Actor types: system, admin, seller, customer.
     */
    public function canTransitionTo(string $toStatus, string $actorType = 'system'): bool
    {
        $fromStatus = (string) $this->status;

        $allowed = [
            self::STATUS_AWAITING_PAYMENT => [
                self::STATUS_PAID => ['customer', 'admin', 'system'],
                self::STATUS_TO_PACK => ['seller', 'admin', 'system'],
                self::STATUS_CANCELLED => ['customer', 'admin', 'system'],
            ],
            self::STATUS_PAID => [
                self::STATUS_TO_PACK => ['seller', 'admin', 'system'],
                self::STATUS_CANCELLED => ['customer', 'seller', 'admin', 'system'],
            ],
            self::STATUS_TO_PACK => [
                self::STATUS_READY_TO_SHIP => ['seller', 'admin', 'system'],
                self::STATUS_CANCELLED => ['customer', 'seller', 'admin', 'system'],
            ],
            self::STATUS_READY_TO_SHIP => [
                self::STATUS_SHIPPED => ['seller', 'admin', 'system'],
                self::STATUS_CANCELLED => ['customer', 'seller', 'admin', 'system'],
            ],
            self::STATUS_PROCESSING => [
                self::STATUS_TO_PACK => ['seller', 'admin', 'system'],
                self::STATUS_SHIPPED => ['seller', 'admin', 'system'],
                self::STATUS_CANCELLED => ['customer', 'seller', 'admin', 'system'],
            ],
            self::STATUS_SHIPPED => [
                self::STATUS_OUT_FOR_DELIVERY => ['seller', 'admin', 'system'],
                self::STATUS_DELIVERED => ['customer', 'admin', 'system'],
            ],
            self::STATUS_OUT_FOR_DELIVERY => [
                self::STATUS_DELIVERED => ['customer', 'admin', 'system'],
            ],
            self::STATUS_DELIVERED => [
                self::STATUS_COMPLETED => ['admin', 'system'],
            ],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (! isset($allowed[$fromStatus][$toStatus])) {
            return false;
        }

        return in_array($actorType, $allowed[$fromStatus][$toStatus], true);
    }

    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            $order->recordStatusHistory(null, $order->status);
        });

        static::updated(function (Order $order): void {
            if ($order->wasChanged('status')) {
                $order->recordStatusHistory($order->getOriginal('status'), $order->status);
            }
        });
    }

    public function recordStatusHistory(?string $fromStatus, ?string $toStatus): void
    {
        if (! Schema::hasTable('order_status_history')) {
            return;
        }

        [$actorType, $actorId] = $this->detectActor();

        DB::table('order_status_history')->insert([
            'order_id' => $this->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    protected function detectActor(): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            return ['admin', $admin->id];
        }

        $seller = Auth::guard('seller')->user();
        if ($seller) {
            return ['seller', $seller->id];
        }

        $customer = Auth::guard('web')->user();
        if ($customer) {
            return ['customer', $customer->id];
        }

        return ['system', null];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function disputes()
    {
        return $this->hasMany(\App\Models\OrderDispute::class);
    }

    public function trackingEvents()
    {
        return $this->hasMany(\App\Models\OrderTrackingEvent::class)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function statusHistory()
    {
        return $this->hasMany(\App\Models\OrderStatusHistory::class)
            ->orderByDesc('created_at');
    }

    public function getFullTrackingTimelineAttribute(): array
    {
        $statusTitles = [
            self::STATUS_AWAITING_PAYMENT => 'Order Placed',
            self::STATUS_PAID             => 'Payment Confirmed',
            self::STATUS_TO_PACK          => 'Seller Preparing Order',
            self::STATUS_READY_TO_SHIP    => 'Ready to Ship',
            self::STATUS_PROCESSING       => 'Processing',
            self::STATUS_SHIPPED          => 'Parcel Handed to Courier',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_DELIVERED        => 'Order Delivered',
            self::STATUS_COMPLETED        => 'Order Completed',
            self::STATUS_CANCELLED        => 'Order Cancelled',
        ];

        $timeline = [];

        foreach ($this->statusHistory as $history) {
            $toStatus = $history->to_status;
            if ($toStatus) {
                $timeline[] = [
                    'type'        => 'status',
                    'title'       => $statusTitles[$toStatus] ?? ucwords(str_replace('_', ' ', $toStatus)),
                    'description' => null,
                    'location'    => null,
                    'occurred_at' => $history->created_at,
                ];
            }
        }

        foreach ($this->trackingEvents as $event) {
            $timeline[] = [
                'type'        => 'courier',
                'title'       => ucwords(str_replace('_', ' ', (string) $event->event_status)),
                'description' => $event->description,
                'location'    => $event->location,
                'occurred_at' => $event->occurred_at ?? $event->created_at,
            ];
        }

        usort($timeline, function (array $a, array $b): int {
            $timeA = $a['occurred_at'] ? \Illuminate\Support\Carbon::parse($a['occurred_at'])->timestamp : 0;
            $timeB = $b['occurred_at'] ? \Illuminate\Support\Carbon::parse($b['occurred_at'])->timestamp : 0;

            return $timeB <=> $timeA;
        });

        return $timeline;
    }

    public function payout()
    {
        return $this->hasOne(\App\Models\SellerPayout::class);
    }
}
