<?php

namespace Leafwrap\PaymentDeals\Services;

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
    static array $response;
    static array $redirectUrls = [
        'success' => '',
        'cancel'  => '',
    ];

    public function getPaymentResponse()
    {
        return BaseService::$response;
    }

    protected function setPaymentResponse($data)
    {
        BaseService::$response = $data;
    }

    protected function checkGatewayCredentials()
    {
        if (!BaseService::$paymentGateway = PaymentGateway::query()->where(['type' => BaseService::$gateway])->first()) {
            return $this->leafwrapResponse(true, false, 'error', 404, 'Payment gateway not found');
        }

        return $this->leafwrapResponse(false, true, 'success', 200, 'Payment gateway found');
    }

    protected function setRedirectionUrls()
    {
        $gateway       = BaseService::$gateway;
        $transactionId = BaseService::$transactionId;

        BaseService::$redirectUrls = [
            'success' => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=success",
            'cancel'  => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=cancel",
        ];
    }

    protected function paymentActivity($data)
    {
        if (!$exist = PaymentTransaction::query()->where(['transaction_id' => BaseService::$transactionId])->first()) {
            $payload = [
                'transaction_id' => BaseService::$transactionId,
                'user_id'        => BaseService::$userId,
                'gateway'        => BaseService::$gateway,
                'amount'         => BaseService::$amount,
                'plan_data'      => BaseService::$planData,
            ];
            PaymentTransaction::query()->create(array_merge($payload, $data));
            return;
        }
        $exist->update($data);
    }

    protected function transactionCheck($transactionId)
    {
        if (!$exist = PaymentTransaction::query()->where(['transaction_id' => $transactionId, 'status' => 'request'])->first()) {
            $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 404, 'Payment transaction not found'));
            return;
        }

        BaseService::$transactionId = $exist->transaction_id;

        if (!in_array($exist->gateway, ['paypal', 'stripe', 'razor_pay', 'bkash'])) {
            $this->setPaymentResponse($this->leafwrapResponse(true, false, 'error', 404, 'Payment transaction invalid gateway'));
            return;
        }

        BaseService::$gateway = $exist->gateway;
        BaseService::$orderId = $exist->request_payload['response']['id'] ?? '';

        $this->setPaymentResponse($this->checkGatewayCredentials());
        if ($this->getPaymentResponse()['isError']) {
            return;
        }

        $this->setPaymentResponse($this->leafwrapResponse(false, true, 'success', 200, 'Transaction validated successfully'));
    }
}
