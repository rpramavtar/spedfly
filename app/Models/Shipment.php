<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'seller_id',
        'order_id',
        'customer_id',
        'shipment_code',
        'shipment_date',
        'customer_name',
        'customer_phone',
        'pickup_name',
        'pickup_address',
        'pickup_city',
        'pickup_postal_code',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_name',
        'delivery_address',
        'delivery_city',
        'delivery_postal_code',
        'delivery_latitude',
        'delivery_longitude',
        'courier_name',
        'weight',
        'dimensions',
        'payment_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'shipment_date' => 'date',
        'weight' => 'decimal:2',
        'pickup_latitude' => 'decimal:7',
        'pickup_longitude' => 'decimal:7',
        'delivery_latitude' => 'decimal:7',
        'delivery_longitude' => 'decimal:7',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
