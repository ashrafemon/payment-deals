<?php

namespace Leafwrap\PaymentDeals\Actions;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\RazorPayService;

class RazorPayAction extends BaseService
{
    private function init()
    {
        $service = new RazorPayService(BaseService::$paymentGateway->credentials['app_key'] ?? '', BaseService::$paymentGateway->credentials['secret_key'] ?? '');

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

        $this->setPaymentResponse($service->orderQuery(BaseService::$orderId));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $payload = $this->getPaymentResponse();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('status', $payload['data']) && $payload['data']['status'] === 'paid') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->getPaymentResponse()['data']]);
        }
    }
}