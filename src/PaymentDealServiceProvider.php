<?php

namespace Leafwrap\PaymentDeals;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class PaymentDealServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel)
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/views', 'payment-deal');
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

    public function register(): void
    {
        $this->registerFacades();
        parent::register(); // TODO: Change the autogenerated stub
    }

    protected function registerFacades(): void
    {
        $this->app->singleton('PaymentDeal', function ($app) {
            return new PaymentDeal();
        });
    }
}
