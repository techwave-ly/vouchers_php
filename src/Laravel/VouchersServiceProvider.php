<?php

namespace Commerce\Vouchers\Laravel;

use Illuminate\Support\ServiceProvider;
use Commerce\Vouchers\VouchersClient;

class VouchersServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(VouchersClient::class, function ($app) {
            $config = $app['config']->get('vouchers', []);
            
            $apiKeyId = $config['api_key_id'] ?? env('VOUCHERS_API_KEY_ID', '');
            $apiSecret = $config['api_secret'] ?? env('VOUCHERS_API_SECRET', '');
            $baseUrl = $config['base_url'] ?? env('VOUCHERS_BASE_URL', 'https://api.wavecommerce.ly');
            $verifySsl = $config['verify_ssl'] ?? env('VOUCHERS_VERIFY_SSL', true);
            
            return new VouchersClient($apiKeyId, $apiSecret, $baseUrl, $verifySsl);
        });

        $this->app->alias(VouchersClient::class, 'vouchers');
    }

    public function boot()
    {
        // For standard implementations, env variables are sufficient
        // A dedicated config file could be published here globally.
    }
}
