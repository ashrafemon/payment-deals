<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Leafwrap\PaymentDeals\Contracts\PaymentContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaypalService implements PaymentContract
{
    use Helper;

    private string $baseUrl;
    private string $requestId;
    private array $tokens;
    private array $urls = [
        'token' => '/v1/oauth2/token',
        'request' => '/v2/checkout/orders',
        'query' => '/v2/checkout/orders/:orderId',
        'execute' => '/v2/checkout/orders/:orderId/capture',
    ];

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
            if (!$this->appKey || !$this->secretKey) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Please provide a valid credentials');
            }

            $url = $this->baseUrl . $this->urls['token'];

            cache()->remember('paypal_token', now()->addHour(), function () use ($url) {
                $client = Http::withBasicAuth($this->appKey, $this->secretKey)
                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                    ->asForm()
                    ->post($url, [
                        'grant_type'                => 'client_credentials',
                        'ignoreCache'               => true,
                        'return_authn_schemes'      => true,
                        'return_client_metadata'    => true,
                        'return_unconsented_scopes' => true,
                    ]);

                if (!$client->successful()) {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal credential configuration problem...', $client->json());
                }

                $client = $client->json();

                if (!array_key_exists('token_type', $client) || !array_key_exists('access_token', $client)) {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal configuration problem...', $client->json());
                }

                $this->tokens = [$client['token_type'] . ' ', $client['access_token']];
            });

            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentRequest($data, $urls)
    {
        try {
            $headers = [
                'Content-Type'      => 'application/json',
                'Prefer'            => 'return=representation',
                'PayPal-Request-Id' => $this->requestId,
                'Authorization'     => $this->tokens[0] . $this->tokens[1],
            ];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withHeaders($headers)
                ->post($url, [
                    'intent'              => 'CAPTURE',
                    'purchase_units'      => [[
                        "reference_id" => uniqid(),
                        "amount"       => ["currency_code" => $data['currency'] ?? 'usd', "value" => (string) $data['amount']],
                    ]],
                    'application_context' => [
                        'return_url' => $urls['success'],
                        'cancel_url' => $urls['cancel'],
                    ],
                ]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('links', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Something went wrong in paypal transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['links'][1]['href']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'Paypal request added successfully...', $payload);
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

            $url = $this->baseUrl . str_replace(':orderId', $orderId, $this->urls['query']);

            $client = Http::withHeaders($headers)->get($url);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            $client = $client->json();

            return $this->paymentConfirm($client, $orderId);
        } catch (Exception $e) {
            return $e;
        }
    }

    private function paymentConfirm($data, $orderId)
    {
        try {
            $headers = [
                'PayPal-Request-Id' => $this->requestId,
                'Prefer'            => 'return=representation',
                'Content-Type'      => 'application/json',
                'Authorization'     => $this->tokens[0] . $this->tokens[1],
            ];

            if (!array_key_exists('status', $data)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $data);
            } else {
                if ($data['status'] === 'PAYER_ACTION_REQUIRED') {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment requires payment. Please pay first...', $data);
                } elseif ($data['status'] === 'COMPLETED') {
                    return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment already paid...', $data);
                }
            }

            $url = $this->baseUrl . str_replace(':orderId', $orderId, $this->urls['execute']);

            $client = Http::withHeaders($headers)->post($url, [
                'application_context' => [
                    'return_url' => '',
                    'cancel_url' => '',
                ],
            ]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Paypal payment request problem...', $client->json());
            }

            Cache::forget('paypal_id');

            return $this->leafwrapResponse(false, true, 'success', 200, 'Paypal order validated successfully...', $client->json());
        } catch (Exception $e) {
            return $e;
        }
    }

    private function requestIdBuilder()
    {
        $this->requestId = cache()->remember('paypal_id', now()->addMinutes(10), fn () => (string) Str::uuid());
    }
}
