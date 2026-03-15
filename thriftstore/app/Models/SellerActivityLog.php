<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['seller_id', 'action', 'details'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public static function log(int $sellerId, string $action, ?array $details = null): void
    {
        static::query()->create([
            'seller_id' => $sellerId,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
