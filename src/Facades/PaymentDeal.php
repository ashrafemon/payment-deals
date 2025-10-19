<?php

namespace Leafwrap\PaymentDeals\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Leafwrap\PaymentDeals\PaymentDeal init(array $planData, float $amount, string $userId, string $gateway, string $currency, float $exchangeRate)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal checkout()
 * @method static \Leafwrap\PaymentDeals\PaymentDeal query(string $transactionId)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal execute(string $transactionId)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal getResponse()
 */
class PaymentDeal extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'PaymentDeal';
    }
}
