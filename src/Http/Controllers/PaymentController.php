<?php

namespace Leafwrap\PaymentDeals\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Leafwrap\PaymentDeals\Facades\PaymentDeal;
use Leafwrap\PaymentDeals\Models\PaymentTransaction;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaymentController extends Controller
{
    use Helper;

    public function check($transactionId)
    {
        try {
            $type = request()->input('type') ?? 'execute';

            if (!$transaction = PaymentTransaction::query()->where(['transaction_id' => $transactionId])->first()) {
                return $this->leafwrapMessage('Transaction not found');
            }

            if ($transaction->status === 'completed' && $type === 'execute') {
                return $this->leafwrapMessage('Payment already completed', 400);
            }

            match ($type) {
                'query' => PaymentDeal::query($transactionId),
                'execute' => PaymentDeal::execute($transactionId),
            };

            $res = PaymentDeal::feedback();

            if ($res['isError']) {
                return $this->leafwrapMessage($res['message'], $res['statusCode']);
            }

            return $this->leafwrapEntity($res['data'], $res['statusCode'], $res['status'], $res['message']);
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }
}
