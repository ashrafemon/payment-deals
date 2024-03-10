<?php

namespace Leafwrap\PaymentDeals\Providers;

use Exception;
use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\ProviderContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class Bkash implements ProviderContract
{
    use Helper;

    private string $baseUrl;
    private string $requestId;
    private array $tokens;
    private array $urls = ['token' => '/v1.2.0-beta/tokenized/checkout/token/grant', 'request' => '/v1.2.0-beta/tokenized/checkout/create', 'query' => '/v1.2.0-beta/tokenized/checkout/payment/status', 'execute' => '/v1.2.0-beta/tokenized/checkout/execute'];

    public function __construct(private string $appKey, private string $secretKey, private string $username, private string $password, private bool $sandbox)
    {
        $this->baseUrlBuilder();
    }

    private function baseUrlBuilder(): void
    {
        $this->baseUrl = match ($this->sandbox) {
            true => 'https://tokenized.sandbox.bka.sh',
            false => 'https://tokenized.pay.bka.sh',
        };
    }

    public function tokenizer(): array
    {
        try {
            if (!$this->appKey || !$this->secretKey || !$this->username || !$this->password) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid credentials');
            }

            $url = $this->baseUrl . $this->urls['token'];

            $headers = ['accept' => 'application/json', 'Content-Type' => 'application/json', 'username' => $this->username, 'password' => $this->password];

            $client = Http::withHeaders($headers)->post($url, ['app_key' => $this->appKey, 'app_secret' => $this->secretKey]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash credential configuration problem...', $client->json());
            }

            $client = $client->json();

            if (!array_key_exists('id_token', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash configuration problem...', $client);
            }

            $this->tokens = ['Bearer ', $client['id_token']];

            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderRequest($data, $urls): array
    {
        try {
            $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => $this->tokens[0] . $this->tokens[1], 'X-APP-Key' => $this->appKey];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withHeaders($headers)->post($url, ['mode' => '0011', 'callbackURL' => $urls['success'], 'payerReference' => uniqid(), 'agreementID' => uniqid(), 'amount' => (string) $data['amount'], 'currency' => strtoupper($data['currency']) ?? 'BDT', 'intent' => 'sale', 'merchantInvoiceNumber' => $data['transaction_id']]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('bkashURL', $client) || !array_key_exists('paymentID', $client)) {
                $message = array_key_exists('statusMessage', $client) ? $client['statusMessage'] : 'Something went wrong in bkash transactions';
                return $this->leafwrapResponse(true, false, 'error', 400, $message, $client);
            }

            $payload = ['response' => $client, 'url' => $client['bkashURL']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'bKash request added successfully...', $payload);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderQuery($orderId): array
    {
        try {
            $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => $this->tokens[0] . $this->tokens[1], 'X-APP-Key' => $this->appKey];

            $url = $this->baseUrl . $this->urls['query'];

            $client = Http::withHeaders($headers)->post($url, ['paymentID' => $orderId]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash payment request problem...', $client->json());
            }

            $client = $client->json();

            if (!array_key_exists('statusCode', $client) || !array_key_exists('statusMessage', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 200, 'bKash payment fetch successfully', $client->json());
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderExecute($orderId): array
    {
        try {
            $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => $this->tokens[0] . $this->tokens[1], 'X-APP-Key' => $this->appKey];

            $url = $this->baseUrl . $this->urls['execute'];

            $client = Http::withHeaders($headers)->post($url, ['paymentID' => $orderId]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'bKash payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 201, 'bKash payment execute successfully...', $client->json());
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }
}
