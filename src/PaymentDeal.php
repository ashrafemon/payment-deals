<?php

namespace Leafwrap\PaymentDeals;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\BkashService;
use Leafwrap\PaymentDeals\Services\PaypalService;
use Leafwrap\PaymentDeals\Services\RazorPayService;
use Leafwrap\PaymentDeals\Services\StripeService;

class PaymentDeal extends BaseService
{
    public function init($planData, $amount, $userId, $gateway, $currency = 'usd', $baseAmount = 0): void
    {
        PaymentDeal::$planData      = $planData;
        BaseService::$currency      = strtolower($currency);
        BaseService::$amount        = $amount;
        BaseService::$userId        = $userId;
        BaseService::$gateway       = $gateway;
        BaseService::$baseAmount    = $baseAmount;
        BaseService::$transactionId = strtoupper(uniqid('trans-'));

        $this->setFeedback($this->verifyCredentials());
        if ($this->feedback()['isError']) {
            return;
        }

        $this->setFeedback($this->verifyCurrency());
        if ($this->feedback()['isError']) {
            return;
        }

        $this->setRedirectionUrls();
    }

    public function pay(): void
    {
        match (BaseService::$gateway) {
            'paypal' => (new PaypalService)->pay(),
            'stripe' => (new StripeService)->pay(),
            'bkash' => (new BkashService)->pay(),
            'razorpay' => (new RazorPayService)->pay(),
            default => $this->setFeedback($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }

    public function query($transactionId): void
    {
        $this->verifyTransaction($transactionId);

        if ($this->feedback()['isError']) {
            return;
        }

        if (!BaseService::$gateway || !BaseService::$orderId) {
            $this->setFeedback($this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid gateway & order id'));
            return;
        }

        match (BaseService::$gateway) {
            'paypal' => (new PaypalService)->check(),
            'stripe' => (new StripeService)->check(),
            'bkash' => (new BkashService)->check(),
            'razorpay' => (new RazorPayService)->check(),
            default => $this->setFeedback($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }

    public function execute($transactionId): void
    {
        $this->verifyTransaction($transactionId);

        if ($this->feedback()['isError']) {
            return;
        }

        if (!BaseService::$gateway || !BaseService::$orderId) {
            $this->setFeedback($this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid gateway & order id'));
            return;
        }

        match (BaseService::$gateway) {
            'paypal' => (new PaypalService)->execute(),
            'stripe' => (new StripeService)->execute(),
            'bkash' => (new BkashService)->execute(),
            'razorpay' => (new RazorPayService)->execute(),
            default => $this->setFeedback($this->leafwrapResponse(true, false, 'error', 400, 'Please select a valid payment gateway'))
        };
    }
}
