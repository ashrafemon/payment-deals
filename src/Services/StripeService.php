<?php

namespace Leafwrap\PaymentDeals\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\PaymentContract;
use Leafwrap\PaymentDeals\Traits\Helper;

class StripeService implements PaymentContract
{
    use Helper;

    private array $tokens;
    private string $baseUrl = 'https://api.stripe.com';
    private array $urls = [
        'token' => null,
        'request' => '/v1/checkout/sessions',
        'query' => '/v1/checkout/sessions/:orderId',
    ];

    public function __construct(private string $secretKey)
    {
    }

    public function tokenBuilder()
    {
        try {
            $this->tokens = ['Bearer ', $this->secretKey];
            return $this->leafwrapResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentRequest($data, $urls)
    {
        try {
            $headers = ['Authorization' => $this->tokens[0] . $this->tokens[1], 'Content-Type' => 'application/x-www-form-urlencoded'];

            $url = $this->baseUrl . $this->urls['request'];

            $client = Http::withHeaders($headers)
                ->asForm()
                ->post($url, [
                    'line_items'  => [[
                        'price_data' => ['currency' => $data['currency'] ?? 'usd', 'product_data' => ['name' => 'Product'], 'unit_amount_decimal' => $data['amount'] * 100],
                        'quantity'   => 1,
                    ]],
                    'mode'        => "payment",
                    'success_url' => $urls['success'],
                    'cancel_url'  => $urls['cancel'],
                ]);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Stripe payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('url', $client)) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Something went wrong in stripe transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['url']];

            return $this->leafwrapResponse(false, true, 'success', 201, 'Stripe request added successfully...', $payload);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentValidate($orderId)
    {
        try {
            $headers = ['Authorization' => $this->tokens[0] . $this->tokens[1]];

            $url = $this->baseUrl . str_replace(':orderId', $orderId, $this->urls['query']);

            $client = Http::withHeaders($headers)->get($url);

            if (!$client->successful()) {
                return $this->leafwrapResponse(true, false, 'error', 400, 'Stripe payment request problem...', $client->json());
            }

            return $this->leafwrapResponse(false, true, 'success', 201, 'Stripe order validated successfully...', $client->json());
        } catch (Exception $e) {
            return $e;
        }
    }
}
