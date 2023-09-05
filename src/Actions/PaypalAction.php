<?php

namespace Leafwrap\PaymentDeals\Actions;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\PaypalService;

class PaypalAction extends BaseService
{
    private function init()
    {
        $service = new PaypalService(
            $this->paymentGateway->credentials['app_key'] ?? '',
            $this->paymentGateway->credentials['secret_key'] ?? '',
            $this->paymentGateway->credentials['sandbox'] ?? true
        );

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

        $this->setPaymentResponse($service->paymentRequest(['currency' => $this->currency, 'amount' => $this->amount], $this->redirectUrls));
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

        $this->setPaymentResponse($service->paymentValidate($this->orderId));
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $payload = $this->getPaymentResponse();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('status', $payload) && $payload['status'] === 'COMPLETED') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->getPaymentResponse()['data']]);
        }
    }
}
