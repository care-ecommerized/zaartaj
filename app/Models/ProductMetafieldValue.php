<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMetafieldValue extends Model
{
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_metafield_id',
        'product_id',
        'key',
        'value',
    ];

    public function metafield(): BelongsTo
    {
        return $this->belongsTo(ProductMetafield::class, 'product_metafield_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
