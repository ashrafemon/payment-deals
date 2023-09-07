<?php

namespace Leafwrap\PaymentDeals\Contracts;

interface PaymentContract
{
    // TODO: Authorization token builder
    public function tokenizer();

    // TODO: Order session request
    public function orderRequest($data, $urls);

    // TODO: Already order payment query
    public function orderQuery($orderId);

    // // TODO: Already order payment execute
    // public function orderExecute($orderId);
}
