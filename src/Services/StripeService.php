<?php

namespace Leafwrap\PaymentDeals\Services;

use Leafwrap\PaymentDeals\Contracts\ServiceContract;
use Leafwrap\PaymentDeals\Providers\Stripe;

class StripeService extends BaseService implements ServiceContract
{
    public function pay(): void
    {
        if (!$service = $this->init()) {
            return;
        }

        $this->setFeedback($service->orderRequest(['currency' => BaseService::$currency, 'amount' => BaseService::$amount], BaseService::$redirectUrls));
        if ($this->feedback()['isError']) {
            return;
        }

        $this->paymentActivity(['request_payload' => $this->feedback()['data']]);
    }

    private function init(): ?Stripe
    {
        $service = new Stripe(BaseService::$paymentGateway->credentials['secret_key'] ?? '');

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

        $this->setFeedback($service->orderQuery(BaseService::$orderId));
        if ($this->feedback()['isError']) {
            return;
        }

        $payload = $this->feedback();
        if ($payload['isSuccess'] && $payload['data'] && array_key_exists('status', $payload['data']) && $payload['data']['status'] === 'complete') {
            $this->paymentActivity(['status' => 'completed', 'response_payload' => $this->feedback()['data']]);
        }
    }
}
