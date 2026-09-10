<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallCenterLog extends Model
{
    protected $fillable = [
        'seller_id',
        'customer_id',
        'order_id',
        'call_code',
        'agent_name',
        'call_type',
        'priority',
        'result',
        'duration_seconds',
        'notes',
        'call_time',
    ];

    protected $casts = [
        'call_time' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
