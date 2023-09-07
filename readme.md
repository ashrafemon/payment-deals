# Payment Deal Library: Empowering Seamless Online Payments with Laravel

The Payment Deal Library is a powerful and versatile Laravel package designed to simplify and enhance online payment processing. Whether you're a business owner, developer, or entrepreneur, this library provides you with a comprehensive toolkit for integrating popular payment gateways seamlessly into your web applications.

### Key Features:

Gateway Diversity: Payment Deal offers support for an array of popular payment gateways, including PayPal, Stripe, Razorpay, bKash, and more. This versatility allows you to cater to a global audience and adapt to various payment preferences.

Laravel Integration: Built specifically for Laravel, this library integrates effortlessly with Laravel-based projects. It leverages Laravel's elegance and robustness to provide a reliable and consistent payment experience.

Simple Configuration: Payment Deal simplifies the setup process with an intuitive configuration system. With just a few lines of code, you can start accepting payments through your chosen gateway.

Security: Security is a top priority, and Payment Deal incorporates the latest security standards to protect sensitive customer data. It ensures PCI compliance and safeguards your transactions.

Extensive Documentation: The library comes with comprehensive documentation and examples, making it easy for developers of all levels to get started and effectively implement payment gateways within their applications.

Customization: Tailor the payment experience to match your brand by customizing payment forms and user interactions. Payment Deal provides flexibility for design and user experience customization.

Error Handling: Robust error handling mechanisms ensure that you can easily troubleshoot and resolve issues, enhancing the reliability of your payment processing.

Ongoing Updates: The Payment Deal Library is actively maintained, meaning you'll receive updates, bug fixes, and support to keep your payment systems running smoothly.

### Requirements:

You should ensure that your web server has the following minimum PHP version and extensions:

- PHP >= 8.0

### Installation:

First, install the PaymentDeal package using the Composer package manager:

```bash
composer require leafwrap/payment-deals
```

#### Database Migrations 

PaymentDeal service provider registers its own database migration directory, so remember to migrate your database after installing the package.

```bash
php artisan migrate
```

### Configuration

#### Gateway Credentials or API Keys

PaymentDeal provide payment gateway configuration api to store credentials in database. Please click the below link to show api documentation.

https://documenter.getpostman.com/view/7667667/2s9YBz3b3S

### Usages

- Create a payment request

```bash
use Leafwrap\PaymentDeals\Facades\PaymentDeal;

Route::post('payment', function () {
    // Fetch your pricing plan
    $plan = PricingPlan::where(['id' => 1])->first()->toArray();
    
    /* 
      Initialize required value to create a payment request
      Paramters: 
        1. Pricing Package // an array
        2. Amount // float or int
        3. User ID // string
        4. Gateway Name // string (ex: paypal, stripe, razorpay, bkash)
        5. Currency // string (ex: usd, inr, bdt)
    */
  
    PaymentDeal::init($plan, $amount, $userId, $gateway, $currency);
    
    // Pay provides you to request a payment
    PaymentDeal::pay();
    
    // Feedback provides you payment url link & payment response
    return PaymentDeal::feedback();
});
```

- Execute your payment

```bash
use Leafwrap\PaymentDeals\Facades\PaymentDeal;

Route::post('payment-query', function () {
    $transactionId = 'trans-{someValue}' // which you get from redirect url params
    
    // Use execute to confirm payment
    PaymentDeal::execute($transactionId);
    
    // Feedback provides you payment response
    return PaymentDeal::feedback();
});
```

- Query your payment

```bash
use Leafwrap\PaymentDeals\Facades\PaymentDeal;

Route::post('payment-query', function () {
    $transactionId = 'trans-{someValue}' // which you get from redirect url params
    
    // Query for payment status
    PaymentDeal::query($transactionId);
    
    // Feedback provides you payment response
    return PaymentDeal::feedback();
});
```

- Assign to your plan

```bash
use Leafwrap\PaymentDeals\Models\PaymentTransaction;

Route::post('assign-plan', function () {
    $transactionId = 'trans-{someValue}' // which you get from redirect url params
    
    if($payment = PaymentTransaction::where(['transaction_id' => $transactionId])->first()){
        /* 
          PaymentTransaction have some data attribute
          id, 
          transaction_id, 
          user_id, 
          gateway, 
          amount, 
          plan_data, 
          request_payload, 
          response_payload, 
          status 
        */      

        if($payment->status === 'completed'){
            // Assign package code (your business logic)
        }
    }
});
```