<?php

namespace Leafwrap\PaymentDeals\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = ['transaction_id', 'user_id', 'gateway', 'amount', 'plan_data', 'request_payload', 'response_payload', 'validate_payload', 'status'];

    protected $casts = ['plan_data' => 'array', 'request_payload' => 'array', 'response_payload' => 'array', 'validate_payload' => 'array'];
}
