<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'description',
        'reference_id',
        'balance_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reference_id');
    }

    /**
     * Intercept description to display clean "recharge" for Stripe checkout payments
     */
    public function getDescriptionAttribute($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (preg_match('/^(recharge|Stripe Top-up)\s*\(Session:/i', $value)) {
            return 'recharge';
        }
        return $value;
    }
}
