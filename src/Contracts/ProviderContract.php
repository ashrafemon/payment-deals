<?php

namespace Leafwrap\PaymentDeals\Contracts;

interface ProviderContract
{
    // TODO: Authorization token builder
    public function tokenizer();

    // TODO: Order session request
    public function orderRequest($data, $urls);

    // TODO: Already order payment query
    public function orderQuery($orderId);
}
