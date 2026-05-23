<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'appointment_id',
        'barber_id',
        'service_price',
        'commission_rate',
        'barber_amount',
        'barbershop_amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'service_price'     => 'decimal:2',
        'commission_rate'   => 'decimal:2',
        'barber_amount'     => 'decimal:2',
        'barbershop_amount' => 'decimal:2',
        'paid_at'           => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}