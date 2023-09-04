<?php

namespace Leafwrap\PaymentDeals\Services\Payments;

interface PaymentContract
{
    // TODO: Authorization token builder
    public function tokenBuilder();

    // TODO: Order session request
    public function paymentRequest($data, $urls);

    // TODO: Already order payment validation
    public function paymentValidate($orderId);
}
