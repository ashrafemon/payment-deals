<?php
namespace Leafwrap\PaymentDeals\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Leafwrap\PaymentDeals\Facades\PaymentDeal;
use Leafwrap\PaymentDeals\Libs\Helper;
use Leafwrap\PaymentDeals\Models\PaymentTransaction;

class PaymentController extends Controller
{
    public function __construct(private readonly Helper $helper)
    {
    }

    public function __invoke(Request $request, $transactionId): array
    {
        try {
            $type = $request->input('type') ?? 'execute';
            if (! $transaction = PaymentTransaction::query()->where(['transaction_id' => $transactionId])->first()) {
                return $this->helper->response(['message' => 'Transaction not found', 'status' => 'error', 'statusCode' => 404]);
            }

            if ($transaction->status === 'completed' && $type === 'execute') {
                return $this->helper->response(['message' => 'Payment already completed', 'status' => 'error', 'statusCode' => 400]);
            }

            match ($type) {
                'query'   => PaymentDeal::query($transactionId),
                'execute' => PaymentDeal::execute($transactionId),
            };

            $res = PaymentDeal::getResponse();
            return $this->helper->response(['status' => $res['status'], 'statusCode' => $res['statusCode'], 'message' => $res['message'], 'data' => $res['data']]);
        } catch (Exception $e) {
            return $this->helper->response(['status' => 'server_error', 'statusCode' => 500, 'message' => $e->getMessage()]);
        }
    }
}
