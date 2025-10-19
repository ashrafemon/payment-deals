<?php
namespace Leafwrap\PaymentDeals\Gateways;

use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\GatewayInterface;
use Leafwrap\PaymentDeals\Libs\Helper;

class RazorPayGateway implements GatewayInterface
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
        return config("payment_gateways.razorpay.urls.production");
    }

    private function getUrl(string $type): string | null
    {
        $keys = ['request', 'query'];
        if (! in_array($type, $keys)) {
            return null;
        }

        $url = match ($type) {
            'request' => config("payment_gateways.razorpay.urls.request"),
            'query'   => config("payment_gateways.razorpay.urls.query"),
        };
        return $this->getBaseUrl() . $url;
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials['app_key']    = $credentials['app_key'];
        $this->credentials['app_secret'] = $credentials['app_secret'];
    }

    public function setCallbackUrls(array $callbackUrls): void
    {
        $this->callbackUrls = $callbackUrls;
    }

    public function token(): array
    {
        if (! $this->credentials['app_key'] || ! $this->credentials['app_secret']) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials');
        }

        $this->tokens = [$this->credentials['app_key'], $this->credentials['app_secret']];
        return $this->helper->funcResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
    }

    public function charge(array $payload): array
    {
        $client = Http::withBasicAuth($this->tokens[0], $this->tokens[1])->withHeaders($this->getHeaders())->post($this->getUrl('request'), [
            "amount"          => $payload['amount'],
            "currency"        => strtoupper($payload['currency']) ?? 'USD',
            "description"     => $payload['description'] ?? '',
            "customer"        => [
                "name"    => $payload['customer']['name'] ?? 'Payment Deal',
                "contact" => $payload['customer']['contact'] ?? '01900000000',
                "email"   => $payload['customer']['email'] ?? 'ashraf.emon143@gmail.com',
            ],
            "notify"          => ["sms" => true, "email" => true],
            "reminder_enable" => true,
            "reference_id"    => uniqid(),
            "expire_by"       => strtotime(now()->addMinutes(20)),
            "accept_partial"  => false,
            "callback_url"    => $this->callbackUrls['success'],
            "callback_method" => 'get',
        ]);

        if (! $client->successful()) {
            $errRes = $client->json();
            if (array_key_exists('error', $errRes) && array_key_exists('description', $errRes['error'])) {
                return $this->helper->funcResponse(true, false, 'error', 400, $errRes['error']['description'], $client->json());
            }
            return $this->helper->funcResponse(true, false, 'error', 400, 'RazorPayGateway payment request problem...', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('short_url', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Something went wrong in razorpay transactions', $client);
        }

        $payload = ['response' => $client, 'url' => $client['short_url']];
        return $this->helper->funcResponse(false, true, 'success', 201, 'RazorPayGateway request added successfully...', $payload);
    }

    public function check(string $orderId): array
    {
        $client = Http::withBasicAuth($this->tokens[0], $this->tokens[1])->withHeaders($this->getHeaders())->get(str_replace(':orderId', $orderId, $this->getUrl('query')));
        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'RazorPayGateway payment request problem...', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 201, 'RazorPayGateway payment fetch successfully', $client->json());
    }

    public function verify(string $orderId): array
    {
        return $this->check($orderId);
    }
}
