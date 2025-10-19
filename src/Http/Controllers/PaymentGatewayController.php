<?php
namespace Leafwrap\PaymentDeals\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Leafwrap\PaymentDeals\Http\Requests\PaymentGatewayStoreRequest;
use Leafwrap\PaymentDeals\Http\Requests\PaymentGatewayUpdateRequest;
use Leafwrap\PaymentDeals\Libs\Helper;
use Leafwrap\PaymentDeals\Models\PaymentGateway;

class PaymentGatewayController extends Controller
{
    public function __construct(private readonly Helper $helper)
    {
    }

    public function index(Request $request)
    {
        try {
            $queries = $request->input();

            $paginateQuery = $this->helper->getPaginateQuery(
                $queries['page'] ?? null,
                $queries['offset'] ?? null,
                $queries['limit'] ?? null
            );
            $fields    = ['id', 'type', 'gateway', 'credentials', 'additional', 'status'];
            $relations = [];

            $fnArr = $this->helper->getQueryFieldRelations($queries);
            if (! empty($fnArr['fields'])) {
                $fields = $fnArr['fields'];
            }
            if (! empty($fnArr['relations'])) {
                $relations = $fnArr['relations'];
            }

            $orderKey   = $queries['order_field'] ?? 'created_at';
            $orderValue = $queries['order_by'] ?? 'asc';

            $docs = PaymentGateway::query()
                ->when(array_key_exists('type', $queries), fn($q) => $q->where('type', $queries['type']))
                ->when(array_key_exists('gateway', $queries), fn($q) => $q->where('gateway', $queries['gateway']))
                ->when(array_key_exists('status', $queries), fn($q) => $q->where('status', $queries['status']))
                ->orderBy($orderKey, $orderValue);

            if (! empty($queries['get_all']) && $queries['get_all']) {
                $docs = $docs->with($relations)->select($fields)->lazyById();
                return $this->helper->response(['data' => $docs]);
            }

            $docs = $this->helper->paginate($docs->with($relations)->select($fields)->paginate(
                perPage: $paginateQuery['offset'],
                page: $paginateQuery['page']
            )->toArray());
            return $this->helper->response(['data' => $docs]);
        } catch (Exception $e) {
            return $this->helper->response(['code' => 500, 'type' => 'server_error', 'data' => $e, 'message' => $e->getMessage()]);
        }
    }

    public function store(PaymentGatewayStoreRequest $request)
    {
        try {
            $gateway = PaymentGateway::query()->create($request->validated());
            return $this->helper->response(['message' => 'Payment gateway created successfully', 'statusCode' => 201, 'status' => 'success', 'data' => $gateway]);
        } catch (Exception $e) {
            return $this->helper->response(['code' => 500, 'type' => 'server_error', 'data' => $e, 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $queries = $request->input();

            $fields    = ['id', 'type', 'gateway', 'credentials', 'additional', 'status'];
            $relations = [];
            $counts    = [];

            $fnArr = $this->helper->getQueryFieldRelations($queries);
            if (! empty($fnArr['fields'])) {
                $fields = $fnArr['fields'];
            }
            if (! empty($fnArr['relations'])) {
                $relations = $fnArr['relations'];
            }
            if (! empty($fnArr['counts'])) {
                $counts = $fnArr['counts'];
            }

            $idKey     = $queries['id_key'] ?? 'id';
            $condition = [$idKey => $id];

            if (! $doc = PaymentGateway::query()->withCount($counts)->with($relations)->select($fields)->where($condition)->first()) {
                return $this->helper->response(['message' => 'Payment gateway not found', 'status' => 'error', 'statusCode' => 404]);
            }
            return $this->helper->response(['data' => $doc]);
        } catch (Exception $e) {
            return $this->helper->response(['code' => 500, 'type' => 'server_error', 'data' => $e, 'message' => $e->getMessage()]);
        }
    }

    public function update(PaymentGatewayUpdateRequest $request, string $id)
    {
        try {
            $condition = ['id' => $id];

            if (! $doc = PaymentGateway::query()->where($condition)->first()) {
                return $this->helper->response(['message' => 'Payment gateway not found', 'status' => 'error', 'statusCode' => 404]);
            }

            $doc->update($request->validated());
            return $this->helper->response(['message' => 'Payment gateway update successfully', 'data' => $doc]);
        } catch (Exception $e) {
            return $this->helper->response(['code' => 500, 'type' => 'server_error', 'data' => $e, 'message' => $e->getMessage()]);
        }
    }

    public function destroy(string $id)
    {
        try {
            $condition = ['id' => $id];
            if (! $query = PaymentGateway::query()->select(['id'])->where($condition)->first()) {
                return $this->response(['message' => 'Payment gateway not found', 'status' => 'error', 'statusCode' => 404]);
            }

            $query->delete();
            return $this->helper->response(['message' => 'Payment gateway deleted successfully']);
        } catch (Exception $e) {
            return $this->helper->response(['code' => 500, 'type' => 'server_error', 'data' => $e, 'message' => $e->getMessage()]);
        }
    }
}
