<?php

namespace Leafwrap\PaymentDeals\Actions;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\StripeService;

class StripeAction extends BaseService
{
    private function init()
    {
        $service = new StripeService(BaseService::$paymentGateway->credentials['secret_key'] ?? '');

        $this->setPaymentResponse($service->tokenBuilder());
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        return $service;
    }

    public function pay()
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setPaymentResponse($service->paymentRequest(['currency' => BaseService::$currency, 'amount' => BaseService::$amount], BaseService::$redirectUrls));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $this->paymentActivity(['request_payload' => $this->getPaymentResponse()['data']]);
    }

    public function orderCheck()
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setPaymentResponse($service->paymentValidate(BaseService::$orderId));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $payload = $this->getPaymentResponse();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('status', $payload) && $payload['status'] === 'complete') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->getPaymentResponse()['data']]);
        }
    }
}
