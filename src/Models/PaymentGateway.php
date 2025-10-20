<?php
namespace Leafwrap\PaymentDeals\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = ['type', 'gateway', 'credentials', 'additional', 'status'];

    protected $casts = ['credentials' => 'array', 'additional' => 'array'];
}
