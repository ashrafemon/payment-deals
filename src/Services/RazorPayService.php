<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\PaymentContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class RazorPayService implements PaymentContract
{
    use Helper;

    private array $tokens;
    private string $baseUrl = 'https://api.razorpay.com';
    private array $urls = [
        'token' => null,
        'request' => '/v1/payment_links',
        'query' => '/v1/payment_links/:orderId',
    ];

    public function __construct(private string $appKey, private string $secretKey)
    {
    }

    public function tokenizer()
    {
        try {
            if (!$this->appKey || !$this->secretKey) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid credentials');
            }

            $this->tokens = [$this->appKey, $this->secretKey];
            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderRequest($data, $urls)
    {
        try {
            $headers = ["Content-Type" => "application/json"];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withBasicAuth($this->tokens[0], $this->tokens[1])
                ->withHeaders($headers)
                ->post($url, [
                    "amount"          => $data['amount'],
                    "currency"        => strtoupper($data['currency']) ?? 'USD',
                    "description"     => $data['description'] ?? '',
                    "customer"        => [
                        "name"    => $data['customer']['name'] ?? 'Payment Deal',
                        "contact" => $data['customer']['contact'] ?? '01900000000',
                        "email"   => $data['customer']['email'] ?? 'ashraf.emon143@gmail.com',
                    ],
                    "notify"          => ["sms" => true, "email" => true],
                    "reminder_enable" => true,
                    "reference_id" => uniqid(),
                    "expire_by" => strtotime(now()->addMinutes(20)),
                    "accept_partial" => false,
                    "callback_url" => $urls['success'],
                    "callback_method" => 'get',
                ]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'RazorPay payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('short_url', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Something went wrong in razorpay transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['short_url']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'RazorPay request added successfully...', $payload);
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }

    public function orderQuery($orderId)
    {
        try {
            $headers = ["Content-Type" => 'application/json'];

            $url = $this->baseUrl . str_replace(':orderId', $orderId, $this->urls['query']);

            $client = Http::withBasicAuth($this->tokens[0], $this->tokens[1])->withHeaders($headers)->get($url);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'RazorPay payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 201, 'RazorPay payment fetch successfully', $client->json());
        } catch (Exception $e) {
            return $this->leafwrapResponse(true, false, 'serverError', 500, $e->getMessage());
        }
    }
}
