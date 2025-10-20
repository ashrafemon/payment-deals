<?php
namespace Leafwrap\PaymentDeals\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Leafwrap\PaymentDeals\Contracts\GatewayInterface;
use Leafwrap\PaymentDeals\Libs\Helper;

class PaypalGateway implements GatewayInterface
{
    private string $requestId;
    private array $tokens;
    private array $callbackUrls = [];
    private array $credentials  = [
        'app_key'    => '',
        'app_secret' => '',
        'is_sandbox' => false,
    ];

    public function __construct(private readonly Helper $helper)
    {
        $this->setRequestId();
    }

    private function getBaseUrl(): string
    {
        $key = $this->credentials['is_sandbox'] ? 'sandbox' : 'production';
        return config("payment_gateways.paypal.urls.$key");
    }

    private function getUrl(string $type): string | null
    {
        $keys = ['token', 'request', 'query', 'execute'];
        if (! in_array($type, $keys)) {
            return null;
        }

        $url = match ($type) {
            'token'   => config("payment_gateways.paypal.urls.token"),
            'request' => config("payment_gateways.paypal.urls.request"),
            'query'   => config("payment_gateways.paypal.urls.query"),
            'execute' => config("payment_gateways.paypal.urls.execute"),
        };
        return $this->getBaseUrl() . $url;
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type'      => 'application/json',
            'Prefer'            => 'return=representation',
            'PayPal-Request-Id' => $this->requestId,
            'Authorization'     => $this->tokens[0] . $this->tokens[1],
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials['app_key']    = $credentials['app_key'];
        $this->credentials['app_secret'] = $credentials['app_secret'];
        $this->credentials['is_sandbox'] = $credentials['is_sandbox'];
    }

    public function setCallbackUrls(array $callbackUrls): void
    {
        $this->callbackUrls = $callbackUrls;
    }

    private function setRequestId(): void
    {
        $id              = (string) Str::uuid();
        $this->requestId = $id;
        session(['paypal_id' => $id]);
    }

    public function token(): array
    {
        if (! $this->credentials['app_key'] || ! $this->credentials['app_secret']) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials');
        }

        $client = Http::withBasicAuth($this->credentials['app_key'], $this->credentials['app_secret'])
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->asForm()
            ->post($this->getUrl('token'), [
                'grant_type'                => 'client_credentials',
                'ignoreCache'               => true,
                'return_authn_schemes'      => true,
                'return_client_metadata'    => true,
                'return_unconsented_scopes' => true,
            ]);
        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PaypalGateway credential configuration problem...', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('token_type', $client) || ! array_key_exists('access_token', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PaypalGateway configuration problem...', $client);
        }

        $this->tokens = [$client['token_type'] . ' ', $client['access_token']];
        return $this->helper->funcResponse(false, true, 'success', 200, 'PaypalGateway token setup successfully', $this->tokens);
    }

    public function charge(array $payload): array
    {
        $client = Http::withHeaders($this->getHeaders())->post($this->getUrl('request'), [
            'intent'              => 'CAPTURE',
            'purchase_units'      => [
                [
                    "reference_id" => uniqid(),
                    "amount"       => [
                        "currency_code" => strtolower($payload['currency']) ?? 'usd',
                        "value"         => (string) $payload['amount'],
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => $this->callbackUrls['success'],
                'cancel_url' => $this->callbackUrls['cancel'],
            ],
        ]);

        if (! $client->successful()) {
            $errRes = $client->json();
            if (array_key_exists('details', $errRes) && count($errRes['details'])) {
                return $this->helper->funcResponse(true, false, 'error', 400, $errRes['details'][0]['description'], $client->json());
            }
            return $this->helper->funcResponse(true, false, 'error', 400, 'PaypalGateway payment request problem...', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('links', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Something went wrong in paypal transactions', $client);
        }

        $payload = ['response' => $client, 'url' => $client['links'][1]['href']];
        return $this->helper->funcResponse(false, true, 'success', 201, 'PaypalGateway request added successfully...', $payload);
    }

    public function check(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->get(str_replace(':orderId', $orderId, $this->getUrl('query')));
        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PaypalGateway payment request problem...', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 200, 'PaypalGateway payment fetch successfully', $client->json());
    }

    public function verify(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->post(str_replace(':orderId', $orderId, $this->getUrl('query')), ['application_context' => ['return_url' => '', 'cancel_url' => '']]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'PaypalGateway payment request problem...', $client->json());
        }
        return $this->helper->funcResponse(false, true, 'success', 201, 'PaypalGateway payment execute successfully...', $client->json());
    }
}
