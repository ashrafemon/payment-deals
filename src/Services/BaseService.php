<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Leafwrap\PaymentDeals\Models\PaymentGateway;
use Leafwrap\PaymentDeals\Models\PaymentTransaction;
use Leafwrap\PaymentDeals\Traits\Helper;

class BaseService
{
    use Helper;

    static string $userId;
    static string $transactionId;
    static string $orderId;
    static string $gateway;
    static string $currency;
    static float $amount;
    static array $planData;
    static mixed $paymentGateway;
    static array $paymentFeedback;
    static array $redirectUrls = ['success' => '', 'cancel' => '',];

    protected function setRedirectionUrls(): void
    {
        $gateway = BaseService::$gateway;
        $transactionId = BaseService::$transactionId;

        BaseService::$redirectUrls = ['success' => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=success", 'cancel' => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=cancel",];
    }

    protected function paymentActivity($data): void
    {
        try {
            if (!$exist = PaymentTransaction::query()->where(['transaction_id' => BaseService::$transactionId])->first()) {
                $payload = ['transaction_id' => BaseService::$transactionId, 'user_id' => BaseService::$userId, 'gateway' => BaseService::$gateway, 'amount' => BaseService::$amount, 'plan_data' => BaseService::$planData,];
                PaymentTransaction::query()->create(array_merge($payload, $data));
                return;
            }
            $exist->update($data);
        } catch (Exception $e) {
            $this->setFeedback($this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage()));
            return;
        }
    }

    protected function setFeedback($data): void
    {
        BaseService::$paymentFeedback = $data;
    }

    protected function verifyTransaction($transactionId): void
    {
        try {
            if (!$exist = PaymentTransaction::query()->where(['transaction_id' => $transactionId, 'status' => 'request'])->first()) {
                $this->setFeedback($this->leafwrapResponse(true, false, 'error', 404, 'Payment transaction not found'));
                return;
            }

            BaseService::$transactionId = $exist->transaction_id;

            if (!in_array($exist->gateway, ['paypal', 'stripe', 'razorpay', 'bkash'])) {
                $this->setFeedback($this->leafwrapResponse(true, false, 'error', 404, 'Payment transaction invalid gateway'));
                return;
            }

            BaseService::$gateway = $exist->gateway;
            BaseService::$orderId = match ($exist->gateway) {
                'bkash' => $exist->request_payload['response']['paymentID'] ?? '',
                default => $exist->request_payload['response']['id'] ?? ''
            };

            $this->setFeedback($this->verifyCredentials());
            if ($this->feedback()['isError']) {
                return;
            }

            $this->setFeedback($this->leafwrapResponse(false, true, 'success', 200, 'Transaction validated successfully'));
        } catch (Exception $e) {
            $this->setFeedback($this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage()));
            return;
        }
    }

    protected function verifyCredentials(): array
    {
        try {
            if (!BaseService::$paymentGateway = PaymentGateway::query()->where(['type' => 'online', 'gateway' => BaseService::$gateway])->first()) {
                return $this->leafwrapResponse(true, false, 'error', 404, 'Payment gateway not found');
            }

            return $this->leafwrapResponse(false, true, 'success', 200, 'Payment gateway found');
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function feedback(): array
    {
        return BaseService::$paymentFeedback;
    }
}
