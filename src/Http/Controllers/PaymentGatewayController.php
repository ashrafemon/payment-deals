<?php

namespace Leafwrap\PaymentDeals\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Leafwrap\PaymentDeals\Http\Requests\PaymentGatewayRequest;
use Leafwrap\PaymentDeals\Models\PaymentGateway;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaymentGatewayController extends Controller
{
    use Helper;

    public function index()
    {
        try {
            $offset    = request()->input('offset') ?? 15;
            $fields    = ['id', 'type', 'gateway', 'credentials', 'additional', 'status'];
            $condition = [];

            $query = PaymentGateway::query();

            if (request()->has('status') && request()->input('status')) {
                $condition['status'] = (int) request()->input('status');
            }

            if (request()->has('type') && request()->input('type')) {
                $condition['type'] = request()->input('type');
            }

            if (request()->has('gateway') && request()->input('gateway')) {
                $condition['gateway'] = request()->input('gateway');
            }

            if (request()->has('get_all') && (int) request()->input('get_all') === 1) {
                $query = $query->select($fields)->where($condition)->get();
            } else {
                $query = $this->leafwrapPaginate($query->select($fields)->where($condition)->latest()->paginate($offset)->toArray());
            }

            return $this->leafwrapEntity($query);
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }

    public function store(PaymentGatewayRequest $request)
    {
        try {
            if (PaymentGateway::query()->create($request->validated())) {
                return $this->leafwrapMessage('Payment gateway created successfully', 201, 'success');
            }
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }

    public function show($id)
    {
        try {
            $fields    = ['id', 'type', 'gateway', 'credentials', 'additional', 'status'];
            $condition = ['id' => $id];

            if (!$query = PaymentGateway::query()->select($fields)->where($condition)->first()) {
                return $this->leafwrapMessage();
            }
            return $this->leafwrapEntity($query);
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }

    public function update(PaymentGatewayRequest $request, $id)
    {
        try {
            $condition = ['id' => $id];
            if (!$query = PaymentGateway::query()->where($condition)->first()) {
                return $this->leafwrapMessage();
            }

            $query->update($request->validated());
            return $this->leafwrapMessage('Payment gateway updated successfully', 200, 'success');
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }

    public function destroy($id)
    {
        try {
            $condition = ['id' => $id];
            if (!$query = PaymentGateway::query()->select(['id'])->where($condition)->first()) {
                return $this->leafwrapMessage();
            }

            $query->delete();
            return $this->leafwrapMessage('Payment gateway deleted successfully', 200, 'success');
        } catch (Exception $e) {
            return $this->leafwrapServerError($e);
        }
    }
}
