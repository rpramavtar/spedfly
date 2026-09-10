<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerCsvImport extends Model
{
    protected $fillable = [
        'seller_id',
        'batch_id',
        'row_number',
        'order_id',
        'customer_name',
        'phone',
        'address',
        'amount',
        'status',
        'error_message',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
