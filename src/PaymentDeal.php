<?php

namespace Leafwrap\PaymentDeals;

use Leafwrap\PaymentDeals\Actions\BkashAction;
use Leafwrap\PaymentDeals\Actions\PaypalAction;
use Leafwrap\PaymentDeals\Actions\RazorPayAction;
use Leafwrap\PaymentDeals\Actions\StripeAction;
use Leafwrap\PaymentDeals\Services\BaseService;

class PaymentDeal extends BaseService
{
    public function init($planData, $amount, $userId, $gateway, $currency = 'usd')
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

    public function pay()
    {
        match (BaseService::$gateway) {
            'paypal' => (new PaypalAction)->pay(),
            'stripe' => (new StripeAction)->pay(),
            'bkash' => (new BkashAction)->pay(),
            'razorpay' => (new RazorPayAction)->pay(),
            default => $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }

    public function query($transactionId)
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
            'paypal' => (new PaypalAction)->check(),
            'stripe' => (new StripeAction)->check(),
            'bkash' => (new BkashAction)->check(),
            'razorpay' => (new RazorPayAction)->check(),
            default => $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }

    public function execute($transactionId)
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
            'paypal' => (new PaypalAction)->execute(),
            'stripe' => (new StripeAction)->execute(),
            'bkash' => (new BkashAction)->execute(),
            'razorpay' => (new RazorPayAction)->execute(),
            default => $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }
}
