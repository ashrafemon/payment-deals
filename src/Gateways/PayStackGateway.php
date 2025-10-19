<?php
namespace Leafwrap\PaymentDeals\Gateways;

use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\GatewayInterface;
use Leafwrap\PaymentDeals\Libs\Helper;

class PayStackGateway implements GatewayInterface
{
    private array $tokens;
    private array $callbackUrls = [];
    private array $credentials  = [
        'app_key'    => '',
        'app_secret' => '',
    ];

    public function __construct(private readonly Helper $helper)
    {
    }

    private function getBaseUrl(): string
    {
        return config("payment_gateways.paystack.urls.production");
    }

    private function getUrl(string $type): string | null
    {
        $keys = ['request', 'query'];
        if (! in_array($type, $keys)) {
            return null;
        }

        $url = match ($type) {
            'request' => config("payment_gateways.paystack.urls.request"),
            'query'   => config("payment_gateways.paystack.urls.query"),
        };
        return $this->getBaseUrl() . $url;
    }

    private function getHeaders(): array
    {
        return [
            "Content-Type"  => "application/json",
            'Authorization' => "Bearer {$this->credentials['app_secret']}",
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

        $this->tokens = [$this->credentials['app_secret']];
        return $this->helper->funcResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
    }

    public function charge(array $payload): array
    {
        $client = Http::withHeaders($this->getHeaders())->post($this->getUrl('request'), [
            "amount"       => $payload['amount'],
            "currency"     => strtoupper($payload['currency']) ?? 'USD',
            "email"        => $payload['email'] ?? 'ashraf.emon143@gmail.com',
            "callback_url" => $this->callbackUrls['success'],
        ]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PayStackGateway payment request problem...', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('status', $client) || ! $client['status']) {
            $message = $client['message'] ?? 'Something went wrong in paystack transactions';
            return $this->helper->funcResponse(true, false, 'error', 400, $message, $client);
        }

        if (! array_key_exists('data', $client) || ! array_key_exists('authorization_url', $client['data'])) {
            $message = $client['message'] ?? 'Something went wrong in paystack transactions';
            return $this->helper->funcResponse(true, false, 'error', 400, $message, $client);
        }

        $payload = ['response' => $client, 'url' => $client['data']['authorization_url']];
        return $this->helper->funcResponse(false, true, 'success', 201, 'PayStackGateway request added successfully...', $payload);
    }

    public function check(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->get(str_replace(':orderId', $orderId, $this->getUrl('query')));
        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PayStackGateway payment request problem...', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 201, 'PayStackGateway payment fetch successfully', $client->json());
    }

    public function verify(string $orderId): array
    {
        return $this->check($orderId);
    }
}
