<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'seller_id',
        'customer_id',
        'external_order_id',
        'amount',
        'payment_type',
        'status',
        'ordered_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'ordered_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallCenterLog::class);
    }

    public function returnRecord(): HasOne
    {
        return $this->hasOne(OrderReturn::class);
    }

    protected static function booted(): void
    {
        static::created(function ($order) {
            if (strtolower((string) $order->status) === 'delivered') {
                static::creditSellerForOrder($order);
            }
            static::chargeCommissionForOrder($order);
        });

        static::updated(function ($order) {
            if ($order->isDirty('status')) {
                static::handleWalletTransaction($order);
                static::chargeCommissionForOrder($order);
            }
        });
    }

    protected static function handleWalletTransaction($order): void
    {
        $newStatus = strtolower((string) $order->status);
        $oldStatus = strtolower((string) $order->getOriginal('status'));

        if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
            static::creditSellerForOrder($order);
        } elseif ($oldStatus === 'delivered' && $newStatus !== 'delivered') {
            static::revertCreditForOrder($order);
        }
    }

    protected static function creditSellerForOrder($order): void
    {
        if (! $order->seller_id) {
            return;
        }

        $alreadyCredited = \App\Models\WalletTransaction::query()
            ->where('type', 'topup')
            ->where('reference_id', $order->id)
            ->exists();

        if ($alreadyCredited) {
            return;
        }

        if ($order->seller) {
            $order->seller->topupWallet(
                (float) $order->amount,
                "Delivered Order #" . ($order->external_order_id ?: $order->id),
                $order->id
            );
        }
    }

    protected static function revertCreditForOrder($order): void
    {
        if (! $order->seller_id) {
            return;
        }

        $alreadyReverted = \App\Models\WalletTransaction::query()
            ->where('type', 'charge')
            ->where('description', 'like', 'Reverted credit for Order%')
            ->where('reference_id', $order->id)
            ->exists();

        if ($alreadyReverted) {
            return;
        }

        $wasCredited = \App\Models\WalletTransaction::query()
            ->where('type', 'topup')
            ->where('reference_id', $order->id)
            ->exists();

        if (!$wasCredited) {
            return;
        }

        if ($order->seller) {
            $order->seller->chargeWallet(
                (float) $order->amount,
                $order->id,
                "Reverted credit for Order #" . ($order->external_order_id ?: $order->id)
            );
        }
    }

    public static function calculateCommission($order): float
    {
        $seller = $order->seller;
        if (! $seller) {
            return 0.0;
        }

        $rules = $seller->feeRules()->where('is_active', true)->get();
        if ($rules->isEmpty()) {
            return 0.0;
        }

        $commission = 0.0;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyOrdersCount = static::query()
            ->where('seller_id', $seller->id)
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->whereBetween('ordered_at', [$monthStart, $monthEnd])
            ->count();

        $monthlyOrdersRules = $rules->where('billing_unit', 'monthly_orders');
        if ($monthlyOrdersRules->isNotEmpty()) {
            foreach ($monthlyOrdersRules as $rule) {
                $min = $rule->min_quantity ?? 0;
                $max = $rule->max_quantity;
                if ($monthlyOrdersCount >= $min && ($max === null || $monthlyOrdersCount <= $max)) {
                    $commission += (float) $rule->rate;
                    break;
                }
            }
        }

        if (! empty($order->external_order_id)) {
            $orderCodesRules = $rules->where('billing_unit', 'order_codes');
            if ($orderCodesRules->isNotEmpty()) {
                $orderCodesCount = static::query()
                    ->where('seller_id', $seller->id)
                    ->whereNotNull('external_order_id')
                    ->where('external_order_id', '!=', '')
                    ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
                    ->whereBetween('ordered_at', [$monthStart, $monthEnd])
                    ->count();

                foreach ($orderCodesRules as $rule) {
                    $min = $rule->min_quantity ?? 0;
                    $max = $rule->max_quantity;
                    if ($orderCodesCount >= $min && ($max === null || $orderCodesCount <= $max)) {
                        $commission += (float) $rule->rate;
                        break;
                    }
                }
            }
        }

        return $commission;
    }

    public static function chargeCommissionForOrder($order): void
    {
        if (! $order->seller_id) {
            return;
        }

        if (strtolower((string) $order->status) === 'lead') {
            return;
        }

        $alreadyCharged = \App\Models\WalletTransaction::query()
            ->where('type', 'charge')
            ->where('reference_id', $order->id)
            ->where('description', 'like', 'Commission for Order%')
            ->exists();

        if ($alreadyCharged) {
            return;
        }

        $commission = static::calculateCommission($order);

        if ($commission > 0 && $order->seller) {
            $order->seller->chargeWallet(
                $commission,
                $order->id,
                "Commission for Order #" . ($order->external_order_id ?: $order->id)
            );
        }
    }
}

