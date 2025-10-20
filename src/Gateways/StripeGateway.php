<?php
namespace Leafwrap\PaymentDeals\Gateways;

use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\GatewayInterface;
use Leafwrap\PaymentDeals\Libs\Helper;

class StripeGateway implements GatewayInterface
{
    private array $tokens;
    private array $callbackUrls = [];
    private array $credentials  = [
        'app_secret' => '',
    ];

    public function __construct(private readonly Helper $helper)
    {
    }

    private function getBaseUrl(): string
    {
        return config("payment_gateways.stripe.urls.production");
    }

    private function getUrl(string $type): string | null
    {
        $keys = ['request', 'query'];
        if (! in_array($type, $keys)) {
            return null;
        }

        $url = match ($type) {
            'request' => config("payment_gateways.stripe.urls.request"),
            'query'   => config("payment_gateways.stripe.urls.query"),
        };
        return $this->getBaseUrl() . $url;
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => $this->tokens[0] . $this->tokens[1],
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials['app_secret'] = $credentials['app_secret'];
    }

    public function setCallbackUrls(array $callbackUrls): void
    {
        $this->callbackUrls = $callbackUrls;
    }

    public function token(): array
    {
        if (! $this->credentials['app_secret']) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials');
        }

        $this->tokens = ['Bearer ', $this->credentials['app_secret']];
        return $this->helper->funcResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
    }

    public function charge(array $payload): array
    {
        $client = Http::withHeaders($this->getHeaders())->asForm()->post($this->getUrl('request'), [
            'line_items'  => [[
                'price_data' => [
                    'currency'            => strtolower($payload['currency']) ?? 'usd',
                    'product_data'        => ['name' => 'Product'],
                    'unit_amount_decimal' => $payload['amount'] * 100,
                ],
                'quantity'   => 1,
            ]],
            'mode'        => "payment",
            'success_url' => $this->callbackUrls['success'],
            'cancel_url'  => $this->callbackUrls['cancel'],
        ]);

        if (! $client->successful()) {
            $errRes = $client->json();
            if (array_key_exists('error', $errRes) && array_key_exists('message', $errRes['error'])) {
                return $this->helper->funcResponse(true, false, 'error', 400, $errRes['error']['message'], $client->json());
            }
            return $this->helper->funcResponse(true, false, 'error', 400, 'StripeGateway payment request problem...', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('url', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Something went wrong in stripe transactions', $client);
        }

        $payload = ['response' => $client, 'url' => $client['url']];
        return $this->helper->funcResponse(false, true, 'success', 201, 'StripeGateway request added successfully...', $payload);
    }

    public function check(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->get(str_replace(':orderId', $orderId, $this->getUrl('query')));
        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'StripeGateway payment request problem...', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 200, 'StripeGateway payment fetch successfully...', $client->json());
    }

    public function verify(string $orderId): array
    {
        return $this->check($orderId);
    }
}
