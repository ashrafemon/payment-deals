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

    public function __construct(private string $secretKey)
    {
    }

    public function tokenBuilder()
    {
        try {
            $this->tokens = ['Bearer ', $this->secretKey];
            return $this->responseGenerator(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentRequest($data, $urls)
    {
        try {
            $headers = ['Authorization' => $this->tokens[0] . $this->tokens[1], 'Content-Type' => 'application/x-www-form-urlencoded'];

            $client = Http::withHeaders($headers)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'line_items'  => [[
                        'price_data' => ['currency' => $dta['currency'] ?? 'usd', 'product_data' => ['name' => 'Product'], 'unit_amount_decimal' => $data['amount'] * 100],
                        'quantity'   => 1,
                    ]],
                    'mode'        => "payment",
                    'success_url' => $urls['success'],
                    'cancel_url'  => $urls['cancel'],
                ]);

            if (!$client->ok()) {
                return $this->responseGenerator(true, false, 'error', 400, 'Stripe payment request problem...', $client->json());
            }

            $client = $client->json();
            if (!array_key_exists('url', $client)) {
                return $this->responseGenerator(true, false, 'error', 400, 'Something went wrong in stripe transactions', $client);
            }

            $payload = ['response' => $client, 'url' => $client['url']];

            return $this->responseGenerator(false, true, 'success', 201, 'Stripe request added successfully...', $payload);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function paymentValidate($orderId)
    {
        try {
            $headers = ['Authorization' => $this->tokens[0] . $this->tokens[1]];

            $client = Http::withHeaders($headers)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$orderId}");

            if (!$client->ok()) {
                return $this->responseGenerator(true, false, 'error', 400, 'Stripe payment request problem...', $client->json());
            }

            return $this->responseGenerator(false, true, 'success', 201, 'Stripe order validated successfully...', $client->json());
        } catch (Exception $e) {
            return $e;
        }
    }
}
