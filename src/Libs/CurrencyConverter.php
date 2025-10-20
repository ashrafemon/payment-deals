<?php

use Illuminate\Support\Facades\Http;

class CurrencyConverter
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('currency_converts.api_key');
    }

    public function currencyConverter($value, $currency): float
    {
        $amount = $value;
        if (strtoupper($currency) !== 'usd') {
            $headers = ["apikey" => $this->apiKey];
            $client  = Http::withHeaders($headers)->get('https://api.freecurrencyapi.com/v1/latest');

            if ($client->successful()) {
                $client = $client->json();

                foreach ($client['data'] as $key => $item) {
                    if ($key === strtoupper($currency)) {
                        $amount = (float) ($amount / $item);
                    }
                }
            }

        }
        return round($amount, 2);
    }
}
