<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
protected $fillable = [
    'name',
    'phone',
    'barbershop_id'
];
public function barbershop()
{
    return $this->belongsTo(Barbershop::class);
}
public function appointments()
{
    return $this->hasMany(Appointment::class);
}
}
