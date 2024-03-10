<?php

namespace Leafwrap\PaymentDeals\Providers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Leafwrap\PaymentDeals\Contracts\ProviderContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class PayStack implements ProviderContract
{
    use Helper;

    private array $tokens;
    private string $baseUrl = 'https://api.paystack.co';
    private array $urls     = ['token' => null, 'request' => '/transaction/initialize', 'query' => '/transaction/verify/:orderId'];

    public function __construct(private string $secretKey)
    {
    }

    public function tokenizer(): array
    {
        try {
            if (!$this->secretKey) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid credentials');
            }

            $this->tokens = [$this->secretKey];
            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderRequest($data, $urls): array
    {
        try {
            $headers = ["Content-Type" => "application/json", 'Authorization' => "Bearer {$this->secretKey}"];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withHeaders($headers)->post($url, ["amount" => $data['amount'], "currency" => strtoupper($data['currency']) ?? 'USD', "email" => $data['email'] ?? 'paymentdeal@test.com', "callback_url" => $urls['success']]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'PayStack payment request problem...', $client->json());
            }

            Log::info($client);
            $client = $client->json();
            if (!array_key_exists('status', $client) || !$client['status']) {
                $message = $client['message'] ?? 'Something went wrong in paystack transactions';
                return $this->leafwrapResponse(true, false, 'error', 400, $message, $client);
            }

            if (!array_key_exists('data', $client) || !array_key_exists('authorization_url', $client['data'])) {
                $message = $client['message'] ?? 'Something went wrong in paystack transactions';
                return $this->leafwrapResponse(true, false, 'error', 400, $message, $client);
            }

            $payload = ['response' => $client, 'url' => $client['data']['authorization_url']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'PayStack request added successfully...', $payload);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderQuery($orderId): array
    {
        try {
            $headers = ["Content-Type" => "application/json", 'Authorization' => "Bearer {$this->secretKey}"];

            $url = $this->baseUrl . str_replace(':orderId', $orderId, $this->urls['query']);

            $client = Http::withHeaders($headers)->get($url);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'PayStack payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 201, 'PayStack payment fetch successfully', $client->json());
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }
}
