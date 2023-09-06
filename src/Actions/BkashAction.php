<?php

namespace Leafwrap\PaymentDeals\Actions;

use Leafwrap\PaymentDeals\Services\BaseService;
use Leafwrap\PaymentDeals\Services\BkashService;

class BkashAction extends BaseService
{
    private function init()
    {
        $service = new BkashService(
            BaseService::$paymentGateway->credentials['app_key'] ?? '',
            BaseService::$paymentGateway->credentials['secret_key'] ?? '',
            BaseService::$paymentGateway->credentials['username'] ?? '',
            BaseService::$paymentGateway->credentials['password'] ?? '',
            BaseService::$paymentGateway->credentials['sandbox'] ?? true
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
        if (
            $payload['isSuccess'] && $payload['data'] &&
            array_key_exists('transactionStatus', $payload['data']) && $payload['data']['transactionStatus'] === 'Completed' &&
            array_key_exists('verificationStatus', $payload['data']) && $payload['data']['verificationStatus'] === 'Complete'
        ) {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->getPaymentResponse()['data']]);
        }
    }
}
