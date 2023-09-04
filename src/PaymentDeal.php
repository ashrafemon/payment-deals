<?php

namespace Leafwrap\PaymentDeals;

use Leafwrap\PaymentDeals\Models\PaymentGateway;
use Leafwrap\PaymentDeals\Models\PaymentTransaction;
use Leafwrap\PaymentDeals\Services\PaypalService;
use Leafwrap\PaymentDeals\Services\StripeService;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaymentDeal
{
    use Helper;

    private string $userId;
    private string $transactionId;
    private string $orderId;
    private string $gateway;
    private float $amount;
    private array $planData;

    private mixed $paymentGateway;
    private bool $exit = false;
    private array $response;
    private array $redirectUrls = [
        'success' => '',
        'cancel'  => '',
    ];

    public function initialize($planData, $amount, $userId, $gateway)
    {
        $this->planData      = $planData;
        $this->amount        = $amount;
        $this->userId        = $userId;
        $this->gateway       = $gateway;
        $this->transactionId = strtoupper(uniqid('TRANS-'));

        $this->setPaymentResponse($this->checkGatewayCredentials());
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->setRedirectionUrls();
    }

    public function checkout()
    {
        if ($this->gateway === 'paypal') {
            $this->paypalPay();
        } elseif ($this->gateway === 'stripe') {
            $this->stripePay();
        }
    }

    public function verify($transactionId)
    {
        $this->transactionCheck($transactionId);

        if ($this->gateway === 'paypal' && $this->orderId) {
            $this->paypalOrderCheck();
        } elseif ($this->gateway === 'stripe' && $this->orderId) {
            $this->stripeOrderCheck();
        }
    }

    private function transactionCheck($transactionId)
    {
        if (!$exist = PaymentTransaction::query()->where(['type' => 'pre', 'transaction_id' => $transactionId])->first()) {
            $this->setPaymentResponse($this->responseGenerator(true, false, 'error', 404, 'Payment transaction not found', null));
            exit();
        }

        $this->transactionId = $exist->transaction_id;

        if (!in_array($exist->gateway, ['paypal', 'stripe', 'razor_pay', 'bkash'])) {
            $this->setPaymentResponse($this->responseGenerator(true, false, 'error', 404, 'Payment transaction invalid gateway', null));
            exit();
        }

        $this->gateway = $exist->gateway;
        $this->orderId = $exist->request_payload['response']['id'] ?? '';

        $this->setPaymentResponse($this->checkGatewayCredentials());
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->setPaymentResponse($this->responseGenerator(false, true, 'success', 200, 'Transaction validated successfully', null));
    }

    public function getPaymentResponse()
    {
        return $this->response;
    }

    private function checkGatewayCredentials()
    {
        if (!$this->paymentGateway = PaymentGateway::query()->where(['type' => $this->gateway])->first()) {
            return $this->responseGenerator(true, false, 'error', 404, 'Payment gateway not found', null);
        }

        return $this->responseGenerator(false, true, 'success', 200, 'Payment gateway found', null);
    }

    private function setRedirectionUrls()
    {
        $this->redirectUrls = [
            'success' => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$this->gateway}&transaction_id={$this->transactionId}&status=success",
            'cancel'  => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$this->gateway}&transaction_id={$this->transactionId}&status=cancel",
        ];
    }

    private function paymentRequestActivity($data)
    {
        $payload = ['transaction_id' => $this->transactionId, 'gateway' => $this->gateway, 'amount' => $this->amount, 'plan_data' => $this->planData];
        PaymentTransaction::query()->create(array_merge($payload, $data));
    }

    private function paypalPay()
    {
        if (!$service = $this->paypalInit()) {
            exit();
        }

        $this->setPaymentResponse($service->paymentRequest(['currency' => 'usd', 'amount' => $this->amount], $this->redirectUrls));
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->paymentRequestActivity(['type' => 'pre', 'request_payload' => $this->getPaymentResponse()['data']]);
    }

    private function stripePay()
    {
        if (!$service = $this->stripeInit()) {
            exit();
        }

        $this->setPaymentResponse($service->paymentRequest(['currency' => 'usd', 'amount' => $this->amount], $this->redirectUrls));
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->paymentRequestActivity(['type' => 'pre', 'request_payload' => $this->getPaymentResponse()['data']]);
    }

    private function paypalOrderCheck()
    {
        if (!$service = $this->paypalInit()) {
            exit();
        }

        $this->setPaymentResponse($service->paymentValidate($this->orderId));
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->paymentRequestActivity(['type' => 'post', 'response_payload' => $this->getPaymentResponse()['data']]);
    }

    private function stripeOrderCheck()
    {
        if (!$service = $this->stripeInit()) {
            exit();
        }

        $this->setPaymentResponse($service->paymentValidate($this->orderId));
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        $this->paymentRequestActivity(['type' => 'post', 'response_payload' => $this->getPaymentResponse()['data']]);
    }

    private function setPaymentResponse($data)
    {
        $this->response = $data;
    }

    private function paypalInit()
    {
        $service = new PaypalService(
            $this->paymentGateway->credentials['app_key'] ?? '',
            $this->paymentGateway->credentials['secret_key'] ?? '',
            $this->paymentGateway->credentials['sandbox'] ?? true
        );

        $this->setPaymentResponse($service->tokenBuilder());
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        return $service;
    }

    private function stripeInit()
    {
        $service = new StripeService($this->paymentGateway->credentials['secret_key'] ?? '');

        $this->setPaymentResponse($service->tokenBuilder());
        if ($this->getPaymentResponse()['isError']) {
            exit();
        }

        return $service;
    }
}
