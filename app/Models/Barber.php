<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Barber extends Model
{
    protected $fillable = ['name', 'phone', 'barbershop_id', 'pix_qr'];
 
    public function barbershop()
    {
        return $this->belongsTo(Barbershop::class);
    }
 
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
 
    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }
}