<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'barbershop_id',
        'name',
        'category',
        'description',
        'price',
        'cost',
        'quantity',
        'min_quantity',
        'unit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'quantity' => 'integer',
        'min_quantity' => 'integer',
    ];

    public function barbershop()
    {
        return $this->belongsTo(Barbershop::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }
}