<?php

namespace Leafwrap\PaymentDeals\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Leafwrap\PaymentDeals\Traits\Helper;

class PaymentGatewayRequest extends FormRequest
{
    use Helper;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'type'        => 'sometimes|required|' . Rule::in(['online', 'offline']),
            'gateway'     => 'sometimes|required|' . Rule::in(['paypal', 'stripe', 'sslcommerz', 'razor_pay', 'bkash']),
            'credentials' => 'sometimes|array',
            'status'      => 'sometimes|required|boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->wantsJson() || $this->ajax()) {
            throw new HttpResponseException($this->leafwrapValidateError($validator->errors()));
        }
        parent::failedValidation($validator);
    }
}
