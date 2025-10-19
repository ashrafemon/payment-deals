<?php
namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Leafwrap\PaymentDeals\Gateways\BkashGateway;
use Leafwrap\PaymentDeals\Gateways\PaypalGateway;
use Leafwrap\PaymentDeals\Gateways\PayStackGateway;
use Leafwrap\PaymentDeals\Gateways\RazorPayGateway;
use Leafwrap\PaymentDeals\Gateways\StripeGateway;
use Leafwrap\PaymentDeals\Libs\Helper;
use Leafwrap\PaymentDeals\Models\PaymentGateway;
use Leafwrap\PaymentDeals\Models\PaymentTransaction;

class PaymentService
{
    private array $allowedGateways;
    private mixed $gateway;

    public function __construct(private readonly Helper $helper)
    {
        $this->allowedGateways = array_keys(config('payment_gateways'));
    }

    private function getIsGatewayAllowed(string $gateway): bool
    {
        if (! in_array($gateway, $this->allowedGateways)) {
            return false;
        }
        return true;
    }

    public function getGateway(string $gateway, array $credentials = [], array $callbackUrls = []): array
    {
        if (! $this->getIsGatewayAllowed($gateway)) {
            return $this->helper->funcResponse(true, false, 'error', 404, 'This payment gateway is not supported.');
        }

        if (empty($credentials)) {
            return $this->helper->funcResponse(true, false, 'error', 404, 'Invalid credentials.');
        }

        $paymentGateway = match ($gateway) {
            'paypal'   => PaypalGateway::class,
            'stripe'   => StripeGateway::class,
            'bkash'    => BkashGateway::class,
            'razorpay' => RazorPayGateway::class,
            'paystack' => PayStackGateway::class,
        };
        $paymentGateway->setCredentials([
            'app_key'    => $credentials['app_key'] ?? '',
            'app_secret' => $credentials['app_key'] ?? '',
            'username'   => $credentials['username'] ?? '',
            'password'   => $credentials['password'] ?? '',
            'is_sandbox' => $credentials['is_sandbox'] ?? false,
        ]);
        $paymentGateway->setCallbackUrls($callbackUrls);

        $tokenRes = $paymentGateway->token();
        if ($tokenRes['isError']) {
            return $this->helper->funcResponse(true, false, 'error', $tokenRes['statusCode'], $tokenRes['message']);
        }

        $this->gateway = $paymentGateway;
        return $this->helper->funcResponse(false, true, 'success', 200, 'Payment gateway assign successfully.', $paymentGateway);
    }

    public function getGatewayCredentials(string $gateway, array $condition = []): array
    {
        if (! $this->getIsGatewayAllowed($gateway)) {
            return $this->helper->funcResponse(true, false, 'error', 404, 'This payment gateway is not supported.');
        }

        if (! $paymentGateway = PaymentGateway::query()->where(array_merge($condition, ['type' => 'online', 'gateway' => $gateway]))->first()) {
            return $this->helper->funcResponse(true, false, 'error', 404, 'Payment gateway not found');
        }

        return $this->helper->funcResponse(false, true, 'success', 200, 'Payment gateway found', $paymentGateway);
    }

    public function getIsCurrencySupported(string $gateway, string $currency, float $amount = 0, float $exchange = 0): array
    {
        $convertedAmount   = $amount;
        $convertedCurrency = $currency;

        $allowedCurrencies = config('payment_gateways.' . $gateway . '.currencies');
        if (! in_array(strtoupper($currency), $allowedCurrencies)) {
            $convertedAmount   = round((float) $amount / (float) $exchange, 2);
            $convertedCurrency = 'USD';
        }
        return $this->helper->funcResponse(false, true, 'success', 200, 'Provided currency is allowed', ['currency' => $convertedCurrency, 'amount' => $convertedAmount]);
    }

    public function getCallbackUrls(string $gateway, string $transactionId)
    {
        $urls = [
            'success' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=success",
            'cancel' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=cancel",
        ];
        return $this->helper->funcResponse(false, true, 'success', 200, 'Callback url fetch successfully', $urls);
    }

    public function transactionActivity(array $data): array
    {
        try {
            if (! $exist = PaymentTransaction::query()->where(['transaction_id' => $data['transactionId']])->first()) {
                $payload = [
                    'transaction_id' => $data['transactionId'],
                    'user_id'        => $data['userId'],
                    'gateway'        => $data['gateway'],
                    'amount'         => $data['amount'],
                    'plan_data'      => $data['planData'],
                    'currency'       => $data['currency'],
                ];
                PaymentTransaction::query()->create(array_merge($payload, $data));
                return $this->helper->funcResponse(false, true, 'success', 200, 'Transaction activity added successfully.');
            }
            $exist->update($data);
            return $this->helper->funcResponse(true, false, 'success', 200, 'Transaction activity updated successfully.');
        } catch (Exception $e) {
            return $this->helper->funcResponse(true, false, 'server_error', 500, $e->getMessage());
        }
    }

    public function fetchTransaction($transactionId): array
    {
        try {
            if (! $exist = PaymentTransaction::query()->where(['transaction_id' => $transactionId, 'status' => 'request'])->first()) {
                return $this->helper->funcResponse(true, false, 'error', 404, 'Transaction not found');
            }

            if (! $this->getIsGatewayAllowed($exist->gateway)) {
                return $this->helper->funcResponse(true, false, 'error', 404, 'Payment transaction invalid gateway');
            }

            $orderId = match ($exist->gateway) {
                'bkash'    => $exist->request_payload['response']['paymentID'] ?? '',
                'paystack' => $exist->request_payload['response']['data']['reference'] ?? '',
                default    => $exist->request_payload['response']['id'] ?? ''
            };

            if (! $orderId) {
                return $this->helper->funcResponse(true, false, 'error', 404, 'Order id not found');
            }

            $gatewayCredentials = $this->getGatewayCredentials($exist->gateway);
            if ($gatewayCredentials['isError']) {
                return $this->helper->funcResponse(true, false, 'error', 404, 'Gateway credentials not found');
            }

            $getGateway = $this->getGateway($exist->gateway, $gatewayCredentials['data']);
            if ($getGateway['isError']) {
                return $this->helper->funcResponse(true, false, 'error', 404, 'Gateway not available');
            }

            $order = $this->gateway->check($orderId);
            if ($order['isError']) {
                return $this->helper->funcResponse(true, false, 'error', 404, $order['message']);
            }

            return $this->helper->funcResponse(false, true, 'success', 200, 'Order found', ['order' => $order['data'], 'id' => $orderId, 'gateway' => $exist->gateway]);
        } catch (Exception $e) {
            return $this->helper->funcResponse(true, false, 'server_error', 500, $e->getMessage());
        }
    }

    public function executeTransaction($transactionId): array
    {
        try {
            $order = $this->fetchTransaction($transactionId);
            if ($order['isError']) {
                return $this->helper->funcResponse(true, false, 'error', 404, $order['message']);
            }

            $execute = $this->gateway->verify($order['data']['id']);
            if ($execute['isError']) {
                return $this->helper->funcResponse(true, false, 'error', 404, $execute['message']);
            }

            return $this->helper->funcResponse(false, true, 'success', 200, 'Transaction execute success', $execute['data']);
        } catch (Exception $e) {
            return $this->helper->funcResponse(true, false, 'server_error', 500, $e->getMessage());
        }
    }
}
