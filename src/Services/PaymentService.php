<?php

namespace App\Services;

use App\Models\Payment\AssignPricingPlan;
use App\Models\Payment\PaymentResponse;
use App\Models\Payment\PrePaymentTransaction;
use App\Models\Payment\ValidatePayment;
use App\Models\Stores\System\Store;
use App\Models\System\PaymentGateway;
use App\Models\System\PricingPlan;
use Illuminate\Support\Facades\Http;

class PaymentService
{
    private $planId;
    private $storeId;
    private $orderId;
    private $userId;
    private $gateway;
    private $transactionId;
    private $paypalRequestId;
    private $tokens      = [];
    private $redirectUrl = [
        'success' => '',
        'cancel'  => '',
    ];
    private $paymentUrl;
    private $pricingPlan;
    private $requestPayload = [];
    private $paymentResponse;
    private $response;
    private $paymentGateway;

    public function initialize($planId, $storeId, $userId, $gateway)
    {
        $this->planId  = $planId;
        $this->storeId = $storeId;
        $this->userId  = $userId;
        $this->gateway = $gateway;
        $this->paypalRequestIdGenerator();
    }

    public function checkout()
    {
        if (!$this->planGenerate()) {
            return $this->getResponse();
        }
        if (!$this->tokenGenerate()) {
            return $this->getResponse();
        }

        $this->paymentRequest();
        $this->dataGenerate();
        $this->orderGenerate();
        $this->urlGenerate();
    }

    public function orderValidate($transactionId)
    {
        $this->transactionId = $transactionId;
        if (!$res = PaymentResponse::query()->where(['transaction_id' => $transactionId])->first()) {
            $this->response = ['status' => 'error', 'statusCode' => 404, 'Payment response not found'];
            return false;
        }
        if (in_array($res->gateway, ['paypal', 'stripe', 'razor_pay', 'bkash'])) {
            $this->gateway = $res->gateway;
            $this->orderId = $res->payload['id'] ?? '';
        }

        if (!$this->tokenGenerate()) {
            return $this->getResponse();
        }

        $this->orderVerify();
    }

    private function planGenerate()
    {
        if (!$store = Store::query()->select([primaryKey(), 'type'])->where([primaryKey() => $this->storeId])->first()) {
            $this->response = ['status' => 'error', 'statusCode' => 404, 'message' => 'Store not found'];
            return false;
        }

        if (!$this->pricingPlan = PricingPlan::query()->where([primaryKey() => $this->planId, 'category' => $store->type, 'status' => 'active'])->first()) {
            $this->response = ['status' => 'error', 'statusCode' => 404, 'message' => 'Plan not found'];
            return false;
        }
        return true;
    }

    private function tokenGenerate()
    {
        // TODO: Implement tokenGenerate() method.
        if (!$this->paymentGateway = PaymentGateway::query()->where(['type' => $this->gateway])->first()) {
            $this->response = ['status' => 'error', 'statusCode' => 404, 'message' => 'Payment gateway not found'];
            return false;
        }

        if ($this->gateway === 'paypal') {
            $baseUrl = match ($this->paymentGateway->credentials['sandbox']) {
                true => 'https://api-m.sandbox.paypal.com',
                false => 'https://api-m.paypal.com',
            };

            $client = Http::withBasicAuth(
                $this->paymentGateway->credentials['app_key'],
                $this->paymentGateway->credentials['secret_key'],
            )->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->asForm()
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);
            $client       = $client->json();
            $this->tokens = [$client['token_type'] . ' ', $client['access_token']];
        } elseif ($this->gateway === 'stripe') {
            $this->tokens = ['Bearer ', $this->paymentGateway->credentials['secret_key']];
        }

        return true;
    }

    private function dataGenerate()
    {
        if ($this->gateway === 'paypal') {
            $this->requestPayload = [
                "reference_id" => uniqid(),
                "amount"       => ["currency_code" => 'usd', "value" => $this->pricingPlan->final_price],
            ];
        } elseif ($this->gateway === 'stripe') {
            $this->requestPayload = [
                'price_data' => ['currency' => 'usd', 'product_data' => ['name' => 'PricingPlan'], 'unit_amount_decimal' => $this->pricingPlan->final_price * 100],
                'quantity'   => 1,
            ];
        }
    }

    private function orderGenerate()
    {
        // TODO: Implement orderGenerate() method.
        if ($this->gateway === 'paypal') {
            $baseUrl = match ($this->paymentGateway->credentials['sandbox']) {
                true => 'https://api-m.sandbox.paypal.com',
                false => 'https://api-m.paypal.com',
            };

            $client = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'Authorization'     => $this->tokens[0] . $this->tokens[1],
                'PayPal-Request-Id' => $this->paypalRequestId,
            ])->post("{$baseUrl}/v2/checkout/orders", [
                'intent'         => 'CAPTURE',
                'purchase_units' => [$this->requestPayload],
                'payment_source' => [
                    'paypal' => [
                        "experience_context" => [
                            "user_action" => "PAY_NOW",
                            'return_url'  => $this->redirectUrl['success'],
                            'cancel_url'  => $this->redirectUrl['cancel'],
                        ],
                    ],
                ],
            ]);
            $this->paymentResponse = $client->json();
        } elseif ($this->gateway === 'stripe') {
            $client = Http::withHeaders([
                'Authorization' => $this->tokens[0] . $this->tokens[1],
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
                'line_items'  => [$this->requestPayload],
                'mode'        => "payment",
                'success_url' => $this->redirectUrl['success'],
                'cancel_url'  => $this->redirectUrl['cancel'],
            ]);
            $this->paymentResponse = $client->json();
        }

        $this->storePaymentResponse();
    }

    private function urlGenerate()
    {
        if ($this->gateway === 'paypal') {
            if (!array_key_exists('links', $this->paymentResponse)) {
                $this->response = ['status' => 'error', 'statusCode' => 400, 'message' => 'Something went wrong in paypal transactions'];
                return false;
            }
            $this->paymentUrl = $this->paymentResponse['links'][1]['href'];
        } elseif ($this->gateway === 'stripe') {
            if (!array_key_exists('url', $this->paymentResponse)) {
                $this->response = ['status' => 'error', 'statusCode' => 400, 'message' => 'Something went wrong in stripe transactions'];
                return false;
            }
            $this->paymentUrl = $this->paymentResponse['url'];
        }

        $this->response = ['status' => 'success', 'statusCode' => 200, 'message' => 'Payment request added successfully...'];
        return true;
    }

    private function orderVerify()
    {
        // TODO: Implement orderCheck() method.
        if ($this->gateway === 'paypal') {
            $baseUrl = match ($this->paymentGateway->credentials['sandbox']) {
                true => 'https://api-m.sandbox.paypal.com',
                false => 'https://api-m.paypal.com',
            };

            $client = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => $this->tokens[0] . $this->tokens[1],
            ])->get("{$baseUrl}/v2/checkout/orders/{$this->orderId}");
            $this->paymentResponse = $client->json();

            // if ($this->paymentResponse['status'] === 'APPROVED') {
            //     $this->paypalRequestIdGenerator();

            //     $client = Http::withHeaders([
            //         'PayPal-Request-Id' => (string) $this->paypalRequestId,
            //         'Authorization'     => $this->tokens[0] . $this->tokens[1],
            //     ])->post("https://api-m.sandbox.paypal.com/v2/checkout/orders/{$this->orderId}/authorize");
            //     $this->paymentResponse = $client->json();
            // }
        } elseif ($this->gateway === 'stripe') {
            $client = Http::withHeaders([
                'Authorization' => $this->tokens[0] . $this->tokens[1],
            ])->get("https://api.stripe.com/v1/checkout/sessions/{$this->orderId}");
            $this->paymentResponse = $client->json();
        }
        $this->storePaymentResponse('post');
        $this->assignPlan();
    }

    private function assignPlan()
    {
        if (!$preTrans = PrePaymentTransaction::query()->where(['transaction_id' => $this->transactionId, 'status' => 'request'])->first()) {
            $this->response = ['message' => 'No payment transaction found', 'status' => 'error', 'statusCode' => 404];
            return;
        }

        if (!in_array($this->paymentResponse['status'], ['complete', 'COMPLETED', 'APPROVED'])) {
            $preTrans->update(['status' => 'cancel']);
            $this->response = ['message' => 'Payment transaction cancelled', 'status' => 'error', 'statusCode' => 400];
            return;
        }
        $preTrans->update(['status' => 'confirm']);

        if (AssignPricingPlan::query()->where(['transaction_id' => $this->transactionId])->exists()) {
            $this->response = ['message' => 'This transaction already done', 'status' => 'error', 'statusCode' => 400];
            return;
        }

        $payload = [
            'store_id'        => $preTrans->store_id,
            'pricing_plan'    => $preTrans->pricing_plan,
            'assign_fee'      => $preTrans->amount,
            'transaction_id'  => $this->transactionId,
            'assign_method'   => 'online',
            'assign_date'     => now(),
            'accessible_date' => now()->addDay(),
        ];

        $payload['accessible_date'] = match ($preTrans->pricing_plan['accessible_type']) {
            'day' => now()->addDays($preTrans->pricing_plan['accessible']),
            'week' => now()->addWeeks($preTrans->pricing_plan['accessible']),
            'month' => now()->addMonths($preTrans->pricing_plan['accessible']),
            'year' => now()->addYears($preTrans->pricing_plan['accessible']),
        };

        AssignPricingPlan::query()->create($payload);
        $this->response = ['message' => 'Your payment has completed, Now login to your store.', 'status' => 'success', 'statusCode' => 201];
    }

    public function getResponse()
    {
        return [
            'response'         => $this->response,
            'payment_response' => $this->paymentResponse,
            'payment_url'      => $this->paymentUrl,
        ];
    }

    private function paymentRequest()
    {
        $preTrans = PrePaymentTransaction::query()->create([
            'store_id'       => $this->storeId,
            'user_id'        => $this->userId,
            'transaction_id' => uniqid('TRANS-'),
            'amount'         => $this->pricingPlan->final_price,
            'pricing_plan'   => $this->pricingPlan,
        ]);

        $this->transactionId = $preTrans->transaction_id;

        $this->redirectUrl = [
            'success' => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$this->gateway}&transaction_id={$this->transactionId}&status=success",
            'cancel'  => request()?->getSchemeAndHttpHost() . "/payment-status?gateway={$this->gateway}&transaction_id={$this->transactionId}&status=cancel",
        ];
    }

    private function storePaymentResponse($type = 'pre')
    {
        if ($type === 'pre') {
            PaymentResponse::query()->create([
                'transaction_id' => $this->transactionId,
                'gateway'        => $this->gateway,
                'payload'        => $this->paymentResponse,
            ]);
        } elseif ($type === 'post') {
            ValidatePayment::query()->create([
                'transaction_id' => $this->transactionId,
                'gateway'        => $this->gateway,
                'payload'        => $this->paymentResponse,
            ]);
        }
    }

    private function paypalRequestIdGenerator()
    {
        $value                 = cache()->remember('pos_paypal_id', now()->addHours(3), fn() => uniqid('POS_PAYPAL_ID-'));
        $this->paypalRequestId = $value;
    }
}
