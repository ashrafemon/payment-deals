<?php
namespace Leafwrap\PaymentDeals\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Leafwrap\PaymentDeals\Libs\Helper;

class PaymentGatewayStoreRequest extends FormRequest
{
    public function __construct(private readonly Helper $helper)
    {
        parent::__construct();
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type'                   => 'required|in:online,offline',
            'gateway'                => 'required|in:bkash,paypal,stripe,razorpay,paystack',
            'credentials'            => 'required|array',
            'credentials.app_key'    => [
                Rule::requiredIf(fn() => in_array($this->gateway, ['bkash', 'paypal', 'razorpay'])), 'string',
            ],
            'credentials.app_secret' => [
                Rule::requiredIf(fn() => in_array($this->gateway, ['bkash', 'paypal', 'razorpay', 'stripe', 'paystack'])), 'string',
            ],
            'credentials.username'   => 'required_if:gateway,bkash|string',
            'credentials.password'   => 'required_if:gateway,bkash|string',
            'credentials.is_sandbox' => [Rule::requiredIf(fn() => in_array($this->gateway, ['bkash', 'paypal'])), 'boolean'],
            'additional'             => 'sometimes|array',
            'status'                 => 'required|boolean',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->wantsJson() || $this->ajax()) {
            throw new HttpResponseException($this->helper->validate($validator->errors()));
        }
        parent::failedValidation($validator);
    }
}
