<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDispute extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_SELLER_REVIEW = 'seller_review';
    public const STATUS_UNDER_ADMIN_REVIEW = 'under_admin_review';
    public const STATUS_RETURN_REQUESTED = 'return_requested';
    public const STATUS_RETURN_IN_TRANSIT = 'return_in_transit';
    public const STATUS_RETURN_RECEIVED = 'return_received';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REFUND_COMPLETED = 'refund_completed';
    public const STATUS_RESOLVED_APPROVED = 'resolved_approved';
    public const STATUS_RESOLVED_REJECTED = 'resolved_rejected';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SELLER_REVIEW,
        self::STATUS_UNDER_ADMIN_REVIEW,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURN_IN_TRANSIT,
        self::STATUS_RETURN_RECEIVED,
        self::STATUS_REFUND_PENDING,
        self::STATUS_REFUND_COMPLETED,
        self::STATUS_RESOLVED_APPROVED,
        self::STATUS_RESOLVED_REJECTED,
        self::STATUS_CLOSED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SELLER_REVIEW,
        self::STATUS_UNDER_ADMIN_REVIEW,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURN_IN_TRANSIT,
        self::STATUS_RETURN_RECEIVED,
        self::STATUS_REFUND_PENDING,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_REFUND_COMPLETED,
        self::STATUS_RESOLVED_APPROVED,
        self::STATUS_RESOLVED_REJECTED,
        self::STATUS_CLOSED,
    ];

    public const REASON_CODES = [
        'item_not_as_described' => 'Item not as described',
        'damaged_item' => 'Damaged item',
        'wrong_item' => 'Wrong item received',
        'missing_items' => 'Missing items/parts',
        'parcel_not_received' => 'Parcel not received',
        'other' => 'Other issue',
    ];

    protected $fillable = [
        'order_id',
        'customer_id',
        'seller_id',
        'reason_code',
        'description',
        'evidence_path',
        'status',
        'seller_response_note',
        'seller_responded_at',
        'admin_resolution_note',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'seller_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    public static function isTerminalStatus(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    public function canTransitionTo(string $toStatus, string $actorType = 'system'): bool
    {
        $fromStatus = (string) $this->status;

        if ($fromStatus === $toStatus && in_array($actorType, ['admin', 'system'], true)) {
            return true;
        }

        $allowed = [
            self::STATUS_OPEN => [
                self::STATUS_SELLER_REVIEW => ['seller', 'admin', 'system'],
                self::STATUS_UNDER_ADMIN_REVIEW => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_SELLER_REVIEW => [
                self::STATUS_UNDER_ADMIN_REVIEW => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_UNDER_ADMIN_REVIEW => [
                self::STATUS_RETURN_REQUESTED => ['admin', 'system'],
                self::STATUS_REFUND_PENDING => ['admin', 'system'],
                self::STATUS_RESOLVED_REJECTED => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_RETURN_REQUESTED => [
                self::STATUS_RETURN_IN_TRANSIT => ['customer', 'admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_RETURN_IN_TRANSIT => [
                self::STATUS_RETURN_RECEIVED => ['seller', 'admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_RETURN_RECEIVED => [
                self::STATUS_REFUND_PENDING => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_REFUND_PENDING => [
                self::STATUS_REFUND_COMPLETED => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_REFUND_COMPLETED => [
                self::STATUS_RESOLVED_APPROVED => ['admin', 'system'],
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_RESOLVED_APPROVED => [
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_RESOLVED_REJECTED => [
                self::STATUS_CLOSED => ['admin', 'system'],
            ],
            self::STATUS_CLOSED => [],
        ];

        if (! isset($allowed[$fromStatus][$toStatus])) {
            return false;
        }

        return in_array($actorType, $allowed[$fromStatus][$toStatus], true);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function resolvedByAdmin()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
