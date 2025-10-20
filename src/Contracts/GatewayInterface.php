<?php
namespace Leafwrap\PaymentDeals\Contracts;

interface GatewayInterface
{
    public function token(): array;
    public function charge(array $payload): array;
    public function check(string $orderId): array;
    public function verify(string $orderId): array;
    public function setCredentials(array $credentials): void;
}
