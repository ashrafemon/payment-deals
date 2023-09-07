<?php

namespace Leafwrap\PaymentDeals\Contracts;

interface ServiceContract
{
    public function pay();

    public function check();

    public function execute();
}
