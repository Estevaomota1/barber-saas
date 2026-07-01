<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarberBlock extends Model
{
    protected $fillable = [
        'barber_id', 'date', 'day_of_week', 'start_time', 'end_time', 'reason',
    ];

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}