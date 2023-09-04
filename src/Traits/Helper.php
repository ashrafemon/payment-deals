<?php

namespace Leafwrap\PaymentDeals\Traits;

trait Helper
{
    public function responseGenerator($isError = false, $isSuccess = true, $status = 'success', $statusCode = 200, $message, $data)
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

    protected function leafwrapPaginate($payload): array
    {
        return [
            'data'         => $payload['data'],
            'current_page' => $payload['current_page'],
            'last_page'    => $payload['last_page'],
            'per_page'     => $payload['per_page'],
            'from'         => $payload['from'],
            'to'           => $payload['to'],
            'total'        => $payload['total'],
        ];
    }

    protected function leafwrapMessage($message = 'No data found', $statusCode = 404, $status = 'error')
    {
        return response(['status' => $status, 'statusCode' => $statusCode, 'message' => $message], $statusCode);
    }

    protected function leafwrapEntity($data = null, $statusCode = 200, $status = 'success', $message = null)
    {
        $payload = ['status' => $status, 'statusCode' => $statusCode, 'data' => $data];

        if ($message) {
            $payload['message'] = $message;
        }

        return response($payload, $statusCode);
    }

    protected function leafwrapServerError($exception)
    {
        return response(['status' => 'server_error', 'statusCode' => 500, 'message' => $exception->getMessage()], 500);
    }

    protected function leafwrapValidateError($data, $override = false)
    {
        $errors       = [];
        $errorPayload = !$override ? $data->getMessages() : $data;

        foreach ($errorPayload as $key => $value) {
            $errors[$key] = $value[0];
        }

        return response(['status' => 'validate_error', 'statusCode' => 422, 'data' => $errors], 422);
    }
}
