<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
   protected $fillable = [
    'client_id',
    'appointment_date',
    'status'
];
public function client()
{
    return $this->belongsTo(Client::class);
}
}
