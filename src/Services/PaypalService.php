<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\PaymentContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaypalService implements PaymentContract
{
    use Helper;

    private string $baseUrl;
    private string $requestId;
    private array $tokens;

    public function __construct(
        private string $appKey,
        private string $secretKey,
        private bool $sandbox
    ) {
        $this->requestIdBuilder();
        $this->baseUrlBuilder();
    }

    private function baseUrlBuilder()
    {
        $this->baseUrl = match ($this->sandbox) {
            true => 'https://api-m.sandbox.paypal.com',
            false => 'https://api-m.paypal.com',
        };
    }

    public function tokenBuilder()
    {
        try {
            $client = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

            if (!$client->ok()) {
                return $this->responseGenerator(true, false, 'error', 400, 'Paypal configuration problem...', $client->json());
            }

            $client = $client->json();

            if (!array_key_exists('token_type', $client) || !array_key_exists('access_token', $client)) {
                return $this->responseGenerator(true, false, 'error', 400, 'Paypal configuration problem...', $client->json());
            }

            $this->tokens = [$client['token_type'] . ' ', $client['access_token']];

            return $this->responseGenerator(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentRequest($data, $urls)
    {
        try {
            $headers = [
                'Content-Type'      => 'application/json',
                'Authorization'     => $this->tokens[0] . $this->tokens[1],
                'PayPal-Request-Id' => $this->requestId,
            ];

            $client = Http::withHeaders($headers)
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent'         => 'CAPTURE',
                    'purchase_units' => [[
                        "reference_id" => uniqid(),
                        "amount"       => ["currency_code" => $data['currency'] ?? 'usd', "value" => $data['amount']],
                    ]],
                    'payment_source' => [
                        'paypal' => [
                            "experience_context" => [
                                "user_action" => "PAY_NOW",
                                'return_url'  => $urls['success'],
                                'cancel_url'  => $urls['cancel'],
                            ],
                        ],
                    ],
                ]);

            if (!$client->ok()) {
                return $this->responseGenerator(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('links', $client)) {
                return $this->responseGenerator(true, false, 'error', 400, 'Something went wrong in paypal transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['links'][1]['href']];

            return $this->responseGenerator(false, true, 'success', 201, 'Paypal request added successfully...', $payload);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentValidate($orderId)
    {
        try {
            $headers = [
                'PayPal-Request-Id' => $this->requestId,
                'Content-Type'      => 'application/json',
                'Authorization'     => $this->tokens[0] . $this->tokens[1],
            ];

            $client = Http::withHeaders($headers)->get("{$this->baseUrl}/v2/checkout/orders/{$orderId}");

            if (!$client->ok()) {
                return $this->responseGenerator(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            return $client->json();
        } catch (Exception $e) {
            return $e;
        }
    }

    // private function paymentConfirm($data, $orderId)
    // {
    //     try {
    //         $headers = [
    //             'PayPal-Request-Id' => $this->requestId,
    //             'Content-Type'      => 'application/json',
    //             'Authorization'     => $this->tokens[0] . $this->tokens[1],
    //         ];

    //         if (!array_key_exists('status', $data) && $data['status'] === 'APPROVED') {
    //             $this->response['isError'] = true;
    //             $this->response['data']    = $data;
    //             $this->response['message'] = 'Paypal payment request problem...';
    //         }

    //         $client = Http::withHeaders($headers)
    //             ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/authorize");

    //         if (!$client->ok()) {
    //             $this->response['isError'] = true;
    //             $this->response['data']    = $client->json();
    //             $this->response['message'] = 'Paypal payment request problem...';
    //         }

    //         return $client->json();
    //     } catch (Exception $e) {
    //         return $e;
    //     }
    // }

    private function requestIdBuilder()
    {
        $value           = cache()->remember('pos_paypal_id', now()->addHours(3), fn() => uniqid('POS_PAYPAL_ID-'));
        $this->requestId = $value;
    }
}
