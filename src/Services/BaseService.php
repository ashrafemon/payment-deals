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
    static array $allowedGateways = ['paypal', 'stripe', 'razorpay', 'bkash'];
    static array $redirectUrls    = ['success' => '', 'cancel' => ''];
    static float $exchangeAmount  = 0;

    protected function setRedirectionUrls(): void
    {
        $gateway       = self::$gateway;
        $transactionId = self::$transactionId;

        self::$redirectUrls = ['success' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=success", 'cancel' => request()?->getSchemeAndHttpHost() . "/online-payment-status?gateway={$gateway}&transaction_id={$transactionId}&status=cancel"];
    }

    protected function paymentActivity($data): void
    {
        try {
            if (!$exist = PaymentTransaction::query()->where(['transaction_id' => self::$transactionId])->first()) {
                $payload = ['transaction_id' => self::$transactionId, 'user_id' => self::$userId, 'gateway' => self::$gateway, 'amount' => self::$amount, 'plan_data' => self::$planData, 'currency' => self::$currency];
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

            if (!in_array($exist->gateway, self::$allowedGateways)) {
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
        if (strtolower(self::$gateway) === 'paypal') {
            $allowedCurrencies = ['AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD'];

            if (!in_array(strtoupper(self::$currency), $allowedCurrencies)) {
                $this->amountCalculate();
                // return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed in ' . strtoupper(self::$gateway));
            }
        } elseif (strtolower(self::$gateway) === 'stripe') {
            $allowedCurrencies = ["USD", "AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BIF", "BMD", "BND", "BOB", "BRL", "BSD", "BWP", "BYN", "BZD", "CAD", "CDF", "CHF", "CLP", "CNY", "COP", "CRC", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HTG", "HUF", "IDR", "ILS", "INR", "ISK", "JMD", "JPY", "KES", "KGS", "KHR", "KMF", "KRW", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MUR", "MVR", "MWK", "MXN", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SEK", "SGD", "SHP", "SLE", "SOS", "SRD", "STD", "SZL", "THB", "TJS", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "UYU", "UZS", "VND", "VUV", "WST", "XAF", "XCD", "XOF", "XPF", "YER", "ZAR", "ZMW"];

            if (!in_array(strtoupper(self::$currency), $allowedCurrencies)) {
                $this->amountCalculate();
                // return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed in ' . strtoupper(self::$gateway));
            }
        } elseif (strtolower(self::$gateway) === 'razorpay') {
            $allowedCurrencies = ["USD", "EUR", "GBP", "SGD", "AED", "AUD", "CAD", "CNY", "SEK", "NZD", "MXN", "HKD", "NOK", "RUB", "ALL", "AMD", "ARS", "AWG", "BBD", "BDT", "BMD", "BND", "BOB", "BSD", "BWP", "BZD", "CHF", "COP", "CRC", "CUP", "CZK", "DKK", "DOP", "DZD", "EGP", "ETB", "FJD", "GIP", "GMD", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "JMD", "KES", "KGS", "KHR", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "MAD", "MDL", "MKD", "MMK", "MNT", "MOP", "MUR", "MVR", "MWK", "MYR", "NAD", "NGN", "NIO", "NOK", "NPR", "PEN", "PGK", "PHP", "PKR", "QAR", "SAR", "SCR", "SLL", "SOS", "SSP", "SVC", "SZL", "THB", "TTD", "TZS", "UYU", "UZS", "YER", "ZAR", "GHS"];

            if (!in_array(strtoupper(self::$currency), $allowedCurrencies)) {
                $this->amountCalculate();
                // return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed in ' . strtoupper(self::$gateway));
            }
        } elseif (strtolower(self::$gateway) === 'bkash') {
            $allowedCurrencies = ["BDT"];

            if (!in_array(strtoupper(self::$currency), $allowedCurrencies)) {
                $this->amountCalculate();
                // return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed in ' . strtoupper(self::$gateway));
            }
        } elseif (strtolower(self::$gateway) === 'paystack') {
            $allowedCurrencies = ["GHS", 'NGN', 'USD', 'ZAR', 'KES'];

            if (!in_array(strtoupper(self::$currency), $allowedCurrencies)) {
                $this->amountCalculate();
                // return $this->leafwrapResponse(true, false, 'error', 400, strtoupper(self::$currency) . ' currency is not allowed in ' . strtoupper(self::$gateway));
            }
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

    private function amountCalculate()
    {
        $exchangeAmount = self::$exchangeAmount <= 0 ? 1 : self::$exchangeAmount;
        self::$amount   = round((float) self::$amount / (float) $exchangeAmount, 2);
        self::$currency = 'usd';
    }
}
