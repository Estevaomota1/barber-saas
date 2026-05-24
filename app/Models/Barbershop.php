<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Barbershop extends Model
{
    protected $fillable = [
        'name',
        'email',
        'slug',
        'phone',
        'address',
        'description',
        'opening_time',
        'closing_time',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($barbershop) {
            if (empty($barbershop->slug)) {
                $barbershop->slug = Str::slug($barbershop->name) . '-' . Str::random(6);
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function barbers()
    {
        return $this->hasMany(Barber::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}