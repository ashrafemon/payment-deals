<?php

namespace Leafwrap\PaymentDeals;

use Leafwrap\PaymentDeals\Actions\PaypalAction;
use Leafwrap\PaymentDeals\Actions\StripeAction;
use Leafwrap\PaymentDeals\Services\BaseService;

class PaymentDeal extends BaseService
{
    public function initialize($planData, $amount, $userId, $gateway, $currency = 'usd')
    {
        BaseService::$planData      = $planData;
        BaseService::$currency      = $currency;
        BaseService::$amount        = $amount;
        BaseService::$userId        = $userId;
        BaseService::$gateway       = $gateway;
        BaseService::$transactionId = strtolower(uniqid('trans-'));

        $this->setPaymentResponse($this->checkGatewayCredentials());
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $this->setRedirectionUrls();
    }

    public function checkout()
    {
        match (BaseService::$gateway) {
            'paypal' => (new PaypalAction)->pay(),
            'stripe' => (new StripeAction)->pay(),
            default => $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }

    public function verify($transactionId)
    {
        $this->transactionCheck($transactionId);

        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        if (!BaseService::$gateway || !BaseService::$orderId) {
            $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid gateway & order id'));
            return;
        }

        match (BaseService::$gateway) {
            'paypal' => (new PaypalAction)->orderCheck(),
            'stripe' => (new StripeAction)->orderCheck(),
            default => $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }
}
