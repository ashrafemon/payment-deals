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
    static array $redirectUrls      = ['success' => '', 'cancel' => ''];
    static array $allowedCurrencies = ['usd', 'bdt', 'inr'];
    static float $baseAmount        = 0;

    protected function setRedirectionUrls(): void
    {
        $gateway       = BaseService::$gateway;
        $transactionId = BaseService::$transactionId;

        BaseService::$redirectUrls = ['success' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=success", 'cancel' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=cancel"];
    }

    protected function paymentActivity($data): void
    {
        try {
            if (!$exist = PaymentTransaction::query()->where(['transaction_id' => BaseService::$transactionId])->first()) {
                $payload = ['transaction_id' => BaseService::$transactionId, 'user_id' => BaseService::$userId, 'gateway' => BaseService::$gateway, 'amount' => BaseService::$amount, 'plan_data' => BaseService::$planData];
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

    protected function verifyCurrency()
    {
        if (!in_array(self::$currency, self::$allowedCurrencies)) {
            return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed');
        }

        if (in_array(BaseService::$gateway, ['bkash', 'rocket', 'sslcommerz', 'nagad'])) {
            BaseService::$amount = match (BaseService::$currency) {
                'bdt' => BaseService::$baseAmount > 0 ? BaseService::$amount * BaseService::$baseAmount : BaseService::$amount,
                'usd' => BaseService::$baseAmount > 0 ? round(BaseService::$amount / BaseService::$baseAmount, 0) : BaseService::$amount,
                default => BaseService::$amount,
            };
        } else {
            BaseService::$amount = match (BaseService::$currency) {
                'usd' => BaseService::$amount,
                'bdt' => BaseService::$baseAmount > 0 ? BaseService::$amount / BaseService::$baseAmount : BaseService::$amount,
                'inr' => BaseService::$amount * 100,
                default => BaseService::$amount,
            };
        }

        return $this->leafwrapResponse(false, true, 'success', 200, 'Provided currency is allowed');
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
