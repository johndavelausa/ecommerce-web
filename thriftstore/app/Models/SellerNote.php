<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerNote extends Model
{
    protected $fillable = ['seller_id', 'note', 'admin_id'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
