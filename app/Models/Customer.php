<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'email',
        'phone',
        'address',
        'status',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallCenterLog::class);
    }
}
