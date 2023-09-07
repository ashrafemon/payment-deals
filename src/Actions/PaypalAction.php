<?php

namespace Leafwrap\PaymentDeals\Actions;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\PaypalService;

class PaypalAction extends BaseService
{
    private function init()
    {
        $service = new PaypalService(
            BaseService::$paymentGateway->credentials['app_key'] ?? '',
            BaseService::$paymentGateway->credentials['secret_key'] ?? '',
            BaseService::$paymentGateway->credentials['sandbox'] ?? true
        );

        $this->setPaymentResponse($service->tokenizer());
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

        $this->setPaymentResponse($service->orderRequest(['currency' => BaseService::$currency, 'amount' => BaseService::$amount], BaseService::$redirectUrls));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $this->paymentActivity(['request_payload' => $this->getPaymentResponse()['data']]);
    }

    public function check()
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setPaymentResponse($service->orderQuery(BaseService::$orderId));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }
    }

    public function execute()
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setPaymentResponse($service->orderExecute(BaseService::$orderId));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $payload = $this->getPaymentResponse();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('status', $payload['data']) && $payload['data']['status'] === 'COMPLETED') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->getPaymentResponse()['data']]);
        }
    }
}
