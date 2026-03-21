<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDispute extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_SELLER_REVIEW = 'seller_review';
    public const STATUS_RETURN_REQUESTED = 'return_requested';
    public const STATUS_RETURN_IN_TRANSIT = 'return_in_transit';
    public const STATUS_RETURN_RECEIVED = 'return_received';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REFUND_COMPLETED = 'refund_completed';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SELLER_REVIEW,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURN_IN_TRANSIT,
        self::STATUS_RETURN_RECEIVED,
        self::STATUS_REFUND_PENDING,
        self::STATUS_REFUND_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SELLER_REVIEW,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURN_IN_TRANSIT,
        self::STATUS_RETURN_RECEIVED,
        self::STATUS_REFUND_PENDING,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_REFUND_COMPLETED,
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

    // Seller explanation codes for non-receipt reports
    public const SELLER_EXPLANATION_CODES = [
        'courier_hijacked' => 'Parcel hijacked/stolen by courier',
        'lost_in_transit' => 'Parcel lost in transit',
        'delivered_to_neighbor' => 'Delivered to neighbor/security',
        'wrong_address' => 'Wrong/incomplete address provided',
        'customer_not_home' => 'Customer not home (multiple delivery attempts)',
        'returned_to_sender' => 'Parcel returned to sender',
        'courier_delay' => 'Courier delay - still in transit',
        'other' => 'Other explanation',
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
        'seller_resolution_action',
        'resolved_at',
        'return_tracking_number',
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
                self::STATUS_SELLER_REVIEW => ['seller', 'system'],
                self::STATUS_RETURN_REQUESTED => ['seller', 'system'],
                self::STATUS_REFUND_PENDING => ['seller', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_SELLER_REVIEW => [
                self::STATUS_RETURN_REQUESTED => ['seller', 'system'],
                self::STATUS_REFUND_PENDING => ['seller', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_RETURN_REQUESTED => [
                self::STATUS_RETURN_IN_TRANSIT => ['customer', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_RETURN_IN_TRANSIT => [
                self::STATUS_RETURN_RECEIVED => ['seller', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_RETURN_RECEIVED => [
                self::STATUS_REFUND_PENDING => ['seller', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_REFUND_PENDING => [
                self::STATUS_REFUND_COMPLETED => ['seller', 'system'],
                self::STATUS_CLOSED => ['seller', 'system'],
            ],
            self::STATUS_REFUND_COMPLETED => [
                self::STATUS_CLOSED => ['seller', 'system'],
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

}
