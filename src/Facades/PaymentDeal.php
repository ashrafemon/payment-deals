<?php

namespace Leafwrap\PaymentDeals\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Leafwrap\PaymentDeals\PaymentDeal initialize(array $planData, float $amount, string $userId, string $gateway)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal checkout()
 * @method static \Leafwrap\PaymentDeals\PaymentDeal verify(string $transactionId)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal getPaymentResponse()
 */

class PaymentDeal extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'PaymentDeal';
    }
}
