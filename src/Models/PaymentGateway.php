<?php

namespace Leafwrap\PaymentDeals\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'gateway',
        'credentials',
        'additional',
        'status',
    ];

    protected $casts = [
        'credentials' => 'array',
        'additional'  => 'array',
    ];
}
