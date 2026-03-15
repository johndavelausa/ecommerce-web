<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReport extends Model
{
    protected $fillable = ['product_id', 'customer_id', 'reason', 'description'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public static function reasonOptions(): array
    {
        return [
            'inappropriate' => 'Inappropriate content',
            'misleading' => 'Misleading description',
            'counterfeit' => 'Counterfeit / fake',
            'wrong_category' => 'Wrong category',
            'spam' => 'Spam',
            'other' => 'Other',
        ];
    }
}
