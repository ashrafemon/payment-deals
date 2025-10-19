<?php
namespace Leafwrap\PaymentDeals\Gateways;

use Illuminate\Support\Facades\Http;
use Leafwrap\PaymentDeals\Contracts\GatewayInterface;
use Leafwrap\PaymentDeals\Libs\Helper;

class BkashGateway implements GatewayInterface
{
    private array $tokens;
    private array $callbackUrls = [];
    private array $credentials  = [
        'app_key'    => '',
        'app_secret' => '',
        'username'   => '',
        'password'   => '',
        'is_sandbox' => false,
    ];

    public function __construct(private readonly Helper $helper)
    {
    }

    private function getBaseUrl(): string
    {
        $key = $this->credentials['is_sandbox'] ? 'sandbox' : 'production';
        return config("payment_gateways.bkash.urls.$key");
    }

    private function getUrl(string $type): string | null
    {
        $keys = ['token', 'request', 'query', 'execute'];
        if (! in_array($type, $keys)) {
            return null;
        }

        $url = match ($type) {
            'token'   => config("payment_gateways.bkash.urls.token"),
            'request' => config("payment_gateways.bkash.urls.request"),
            'query'   => config("payment_gateways.bkash.urls.query"),
            'execute' => config("payment_gateways.bkash.urls.execute"),
        };
        return $this->getBaseUrl() . $url;
    }

    private function getHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => $this->tokens[0] . $this->tokens[1],
            'X-APP-Key'     => $this->credentials['app_key'],
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials['app_key']    = $credentials['app_key'];
        $this->credentials['app_secret'] = $credentials['app_secret'];
        $this->credentials['username']   = $credentials['username'];
        $this->credentials['password']   = $credentials['password'];
        $this->credentials['is_sandbox'] = $credentials['is_sandbox'];
    }

    public function setCallbackUrls(array $callbackUrls): void
    {
        $this->callbackUrls = $callbackUrls;
    }

    public function token(): array
    {
        if (! $this->credentials['app_key'] || ! $this->credentials['app_secret'] || ! $this->credentials['username'] || ! $this->credentials['password']) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials');
        }

        $headers = [
            'accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'username'     => $this->credentials['username'],
            'password'     => $this->credentials['password'],
        ];
        $client = Http::withHeaders($headers)->post($this->getUrl('token'), [
            'app_key'    => $this->credentials['app_key'],
            'app_secret' => $this->credentials['app_secret'],
        ]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('id_token', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'Please provide a valid credentials', $client);
        }

        $this->tokens = ['Bearer ', $client['id_token']];
        return $this->helper->funcResponse(false, true, 'success', 200, 'Authorization token setup successfully', $this->tokens);
    }

    public function charge(array $payload): array
    {
        $client = Http::withHeaders($this->getHeaders())->post($this->getUrl('request'), [
            'mode'                  => '0011',
            'intent'                => 'sale',
            'agreementID'           => uniqid(),
            'payerReference'        => uniqid(),
            'amount'                => (string) $payload['amount'],
            'currency'              => strtoupper($payload['currency']) ?? 'BDT',
            'merchantInvoiceNumber' => $payload['transaction_id'],
            'callbackURL'           => $this->callbackUrls['success'],
        ]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'There have been some issues with the transaction.', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('bkashURL', $client) || ! array_key_exists('paymentID', $client)) {
            $message = array_key_exists('statusMessage', $client) ? $client['statusMessage'] : 'There have been some issues with the transaction.';
            return $this->helper->funcResponse(true, false, 'error', 400, $message, $client);
        }

        $payload = ['response' => $client, 'url' => $client['bkashURL']];
        return $this->helper->funcResponse(false, true, 'success', 201, 'The transaction request was successfully added', $payload);
    }

    public function check(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->post($this->getUrl('query'), ['paymentID' => $orderId]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'There have been some issues with the transaction.', $client->json());
        }

        $client = $client->json();
        if (! array_key_exists('statusCode', $client) || ! array_key_exists('statusMessage', $client)) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'There have been some issues with the transaction.', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 200, 'Transaction payment fetch successfully', $client->json());
    }

    public function verify(string $orderId): array
    {
        $client = Http::withHeaders($this->getHeaders())->post($this->getUrl('execute'), ['paymentID' => $orderId]);

        if (! $client->successful()) {
            return $this->helper->funcResponse(true, false, 'error', 400, 'There have been some issues with the transaction.', $client->json());
        }

        return $this->helper->funcResponse(false, true, 'success', 201, 'Transaction execute successfully', $client->json());
    }
}
