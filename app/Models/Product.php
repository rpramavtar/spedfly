<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'seller_id',
        'sku',
        'name',
        'description',
        'category',
        'price',
        'stock',
        'low_stock_alert',
        'image_path',
        'status',
        'shopify_product_id',
        'shopify_sync_status',
        'shopify_synced_at',
        'shopify_sync_error',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'low_stock_alert' => 'integer',
        'shopify_product_id' => 'integer',
        'shopify_synced_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = trim((string) $this->image_path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
