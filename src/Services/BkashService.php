<?php

namespace Leafwrap\PaymentDeals\Services;

use Leafwrap\PaymentDeals\Contracts\ServiceContract;
use Leafwrap\PaymentDeals\Providers\Bkash;

class BkashService extends BaseService implements ServiceContract
{
    public function pay(): void
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setFeedback($service->orderRequest(['currency' => BaseService::$currency, 'amount' => BaseService::$amount, 'transaction_id' => BaseService::$transactionId], BaseService::$redirectUrls));
        if ($this->feedback()['isError']) {
            return;
        }

        $this->paymentActivity(['request_payload' => $this->feedback()['data']]);
    }

    private function init(): ?Bkash
    {
        $service = new Bkash(BaseService::$paymentGateway->credentials['app_key'] ?? '', BaseService::$paymentGateway->credentials['secret_key'] ?? '', BaseService::$paymentGateway->credentials['username'] ?? '', BaseService::$paymentGateway->credentials['password'] ?? '', BaseService::$paymentGateway->credentials['sandbox'] ?? true);

        $this->setFeedback($service->tokenizer());
        if ($this->feedback()['isError']) {
            return null;
        }

        return $service;
    }

    public function check(): void
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setFeedback($service->orderQuery(BaseService::$orderId));
    }

    public function execute(): void
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setFeedback($service->orderExecute(BaseService::$orderId));
        if ($this->feedback()['isError']) {
            return;
        }

        $payload = $this->feedback();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('transactionStatus', $payload['data']) && $payload['data']['transactionStatus'] === 'Completed') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->feedback()['data']]);
        }
    }
}
