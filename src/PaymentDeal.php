<?php
namespace Leafwrap\PaymentDeals;

use Leafwrap\PaymentDeals\Services\PaymentService;

class PaymentDeal
{
    private bool $canProcess = true;
    private string $currency;
    private float $amount;
    private string $transactionId;
    private array $response;
    private mixed $gateway;

    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function init(array $planData, float $amount, string $userId, string $gateway, string $currency = 'usd', float $exchangeRate = 0): void
    {
        $this->transactionId = strtoupper(uniqid('trans_'));

        $gatewayCredentials = $this->paymentService->getGatewayCredentials($gateway);
        if ($gatewayCredentials['isError']) {
            $this->canProcess = false;
            return;
        }
        $credentials = $gatewayCredentials['data'];

        $currencyAmount = $this->paymentService->getIsCurrencySupported($gateway, $currency, $amount, $exchangeRate);
        if ($currencyAmount['isError']) {
            $this->canProcess = false;
            $this->response   = $currencyAmount;
            return;
        }
        $this->currency = $currencyAmount['data']['currency'];
        $this->amount   = $currencyAmount['data']['amount'];

        $callbackUrls = $this->paymentService->getCallbackUrls($gateway, $this->transactionId);
        if ($callbackUrls['isError']) {
            $this->canProcess = false;
            $this->response   = $callbackUrls;
            return;
        }

        $paymentGateway = $this->paymentService->getGateway($gateway, $credentials, $callbackUrls['data']);
        if ($paymentGateway['isError']) {
            $this->canProcess = false;
            $this->response   = $paymentGateway;
            return;
        }
        $this->gateway = $paymentGateway['data'];

        $activity = $this->paymentService->transactionActivity([
            'transactionId' => $this->transactionId,
            'amount'        => $this->amount,
            'currency'      => $this->currency,
            'planData'      => $planData,
            'userId'        => $userId,
            'gateway'       => $gateway,
        ]);
        if ($activity['isError']) {
            $this->canProcess = false;
            $this->response   = $activity;
        }
    }

    public function checkout(): void
    {
        if (! $this->canProcess) {
            return;
        }
        $this->response = $this->gateway->charge([
            'currency'       => $this->currency,
            'amount'         => $this->amount,
            'transaction_id' => $this->transactionId,
        ]);
    }

    public function query($transactionId): void
    {
        $this->response = $this->paymentService->fetchTransaction($transactionId);
    }

    public function execute($transactionId): void
    {
        $this->response = $this->paymentService->executeTransaction($transactionId);
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
