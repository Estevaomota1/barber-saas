<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'barber_id',
        'appointment_date',
        'status',
        'price',
        'service_name',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'appointment_date' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }
}