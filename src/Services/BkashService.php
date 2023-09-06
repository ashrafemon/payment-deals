<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\PaymentContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class BkashService implements PaymentContract
{
    use Helper;

    private string $baseUrl;
    private string $requestId;
    private array $tokens;
    private array $urls = [
        'token' => '/v1.2.0-beta/tokenized/checkout/token/grant',
        'request' => '/v1.2.0-beta/tokenized/checkout/create',
        'query' => '/v1.2.0-beta/tokenized/checkout/payment/status',
        'execute' => '/v1.2.0-beta/tokenized/checkout/execute',
    ];

    public function __construct(
        private string $appKey,
        private string $secretKey,
        private string $username,
        private string $password,
        private bool $sandbox
    ) {
        $this->baseUrlBuilder();
    }

    private function baseUrlBuilder()
    {
        $this->baseUrl = match ($this->sandbox) {
            true => 'https://tokenized.sandbox.bka.sh',
            false => 'https://tokenized.pay.bka.sh',
        };
    }

    public function tokenBuilder()
    {
        try {
            if (!$this->appKey || !$this->secretKey || !$this->username || !$this->password) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid credentials');
            }

            $url = $this->baseUrl . $this->urls['token'];

            cache()->remember('bKash_token', now()->addHour(), function () use ($url) {
                $headers = [
                    'accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'username'     => $this->username,
                    'password'     => $this->password,
                ];

                $client = Http::withHeaders($headers)->post($url, ['app_key' => $this->appKey, 'app_secret' => $this->secretKey]);

                if (!$client->successful()) {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'bKash credential configuration problem...', $client->json());
                }

                $client = $client->json();

                if (!array_key_exists('id_token', $client)) {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'bKash configuration problem...', $client->json());
                }

                $this->tokens = ['Bearer ', $client['id_token']];
            });

            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function paymentRequest($data, $urls)
    {
        try {
            $headers = [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => $this->tokens[0] . $this->tokens[1],
                'X-APP-Key'     => $this->appKey,
            ];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withHeaders($headers)->post($url, [
                'mode'                  => '0011',
                'callbackURL'           => $urls['success'],
                'payerReference'        => uniqid(),
                'agreementID'           => uniqid(),
                'amount'                => (string) $data['amount'],
                'currency'              => strtoupper($data['currency']) ?? 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $data['transaction_id'],
            ]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Bkash payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('bkashURL', $client) || !array_key_exists('paymentID', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Something went wrong in bkash transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['bkashURL']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'Bkash request added successfully...', $payload);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function paymentValidate($orderId)
    {
        try {
            $headers = [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => $this->tokens[0] . $this->tokens[1],
                'X-APP-Key'     => $this->appKey,
            ];

            $url = $this->baseUrl . $this->urls['query'];

            $client = Http::withHeaders($headers)->post($url, ['paymentID' => $orderId]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            $client = $client->json();

            if (!array_key_exists('statusCode', $client) || !array_key_exists('statusMessage', $client)) {
                $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());;
            }

            return $this->paymentConfirm($client, $orderId);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    private function paymentConfirm($data, $orderId)
    {
        try {
            $headers = [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => $this->tokens[0] . $this->tokens[1],
                'X-APP-Key'     => $this->appKey,
            ];

            if (!array_key_exists('statusCode', $data) || !array_key_exists('statusMessage', $data)) {
                $this->leafwrapResponse(true, false, 'error', 400, 'Bkash payment request problem...', $data);;
            }

            $url = $this->baseUrl . $this->urls['execute'];

            $client = Http::withHeaders($headers)->post($url, ['paymentID' => $orderId]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Bkash payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 200, 'Bkash order validated successfully...', $client->json());
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }
}
