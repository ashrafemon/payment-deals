<?php

namespace Leafwrap\PaymentDeals\Traits;

trait Helper
{
    public function responseGenerator($isError = false, $isSuccess = true, $status = 'success', $statusCode = 200, $message, $data)
    {
        return [
            'isError' => $isError,
            'isSuccess' => $isSuccess,
            'status' => $status,
            'statusCode' => $statusCode,
            'message' => $message,
            'data' => $data
        ];
    }
}
