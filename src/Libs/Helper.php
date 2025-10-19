<?php
namespace Leafwrap\PaymentDeals\Libs;

class Helper
{
    public function funcResponse($isError = false, $isSuccess = true, $status = 'success', $statusCode = 200, $message = null, $data = null): array
    {
        return [
            'isError'    => $isError,
            'isSuccess'  => $isSuccess,
            'status'     => $status,
            'statusCode' => $statusCode,
            'message'    => $message,
            'data'       => $data,
        ];
    }

    public function response(array $props)
    {
        return response([
            'status'     => $props['status'] ?? 'success',
            'statusCode' => $props['statusCode'] ?? 200,
            'message'    => $props['message'] ?? null,
            'data'       => $props['data'] ?? null,
        ], $props['statusCode'] ?? 200);
    }

    public function validate($data, $override = false)
    {
        $errors       = [];
        $errorPayload = ! $override ? $data->getMessages() : $data;
        foreach ($errorPayload as $key => $value) {
            $errors[$key] = $value[0];
        }

        return $this->response(['status' => 'validate_error', 'statusCode' => 422, 'message' => 'Validate error occurred...', 'data' => $errors]);
    }

    public function paginate($data)
    {
        return [
            'meta'   => [
                'total'        => $data['total'],
                'current_page' => $data['current_page'],
                'last_page'    => $data['last_page'],
                'per_page'     => $data['per_page'],
                'from'         => $data['from'],
                'to'           => $data['to'],
            ],
            'result' => $data['data'],
        ];
    }

    public function getPaginateQuery(?int $page, ?int $offset, ?int $limit)
    {
        if (! empty($limit)) {
            $o = $limit ?? 10;
            $s = $offset ?? 0;
            $p = floor($s / $o) + 1;
            return ['page' => $p, 'offset' => $o, 'skip' => $s, 'take' => $o];
        }

        $p = $page ?? 1;
        $o = $offset ?? 10;
        $s = ($p - 1) * $o;
        return ['page' => $p, 'offset' => $o, 'skip' => $s, 'take' => $o];
    }

    public function getQueryFieldRelations($queries = [])
    {
        $fields    = [];
        $relations = [];
        $counts    = [];

        if (! empty($queries['fields'])) {
            $fields = gettype($queries['fields']) === 'array' ? $queries['fields'] : explode(',', $queries['fields']);
        }

        if (! empty($queries['relations'])) {
            $relations = gettype($queries['relations']) === 'array' ? $queries['relations'] : explode(',', $queries['relations']);
        }

        if (! empty($queries['counts'])) {
            $counts = gettype($queries['counts']) === 'array' ? $queries['counts'] : explode(',', $queries['counts']);
        }

        return ['fields' => $fields, 'relations' => $relations, 'counts' => $counts];
    }

}
