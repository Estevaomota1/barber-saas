<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 /*ajuste   */
class Barber extends Model
{
    protected $fillable = ['name', 'phone', 'barbershop_id', 'pix_qr', 'pix_key', 'photo'];
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
    public function blocks()
{
    return $this->hasMany(BarberBlock::class);
}
}