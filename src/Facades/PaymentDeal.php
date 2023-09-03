<?php

namespace Leafwrap\PaymentDeals\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Leafwrap\PaymentDeals\PaymentDeal certify(string $permission, string $message = null)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal demonstrate(array $role = null)
 * @method static \Leafwrap\PaymentDeals\PaymentDeal getPermissions()
 * @method static \Leafwrap\PaymentDeals\PaymentDeal getModulePermissions()
 */

class PaymentDeal extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'PaymentDeal';
    }
}
