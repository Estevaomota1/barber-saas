<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'barbershop_id',
        'barber_id',
        'service_id',
        'appointment_date',
        'status',
        'price',
        'service_name',
        'client_name',
        'client_phone',
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

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function barbershop()
    {
        return $this->belongsTo(Barbershop::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }
}