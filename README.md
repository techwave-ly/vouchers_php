# Commerce Vouchers PHP SDK

A robust, dependency-free PHP client SDK created for Commerce partners. Plugs smoothly into generic PHP, Symfony, and acts as a primary standard via a native `ServiceProvider` hook for **Laravel**.

## Features

- **No bloated package requirements**: Built purely upon native `json` and `curl`. Compatible with PHP 8.0+.
- Guaranteed idempotency execution behind-the-scenes.
- Handles authorization implicitly via standard HTTP `$apiKeyId` and `$apiSecret` injection.
- Plug & Play Laravel Facade.

## Installation

Inside your project root directory containing `composer.json`, simply require:

```bash
composer require techwave/commerce-vouchers-php
```

## Quick Start (Generic/Vanilla PHP)

```php
use Commerce\Vouchers\VouchersClient;
use Commerce\Vouchers\Exceptions\APIError;

$client = new VouchersClient(
    'your_api_key', 
    'your_api_secret', 
    'https://api.wavecommerce.ly'
);

try {
    // 1. Enter sandbox API mode safely
    $modeRes = $client->switchMode('test');
    echo "Mode Switched: " . $modeRes['mode'] . "\n";

    // 2. Issue a Voucher (Defaults natively to LYD)
    $issueRes = $client->issueVoucher(100.0);
    $voucherId = $issueRes['voucher']['id'];
    echo "Test Voucher Created! Code: " . $issueRes['voucher']['code'] . "\n";

    // 3. Status validation
    $statusRes = $client->getVoucherStatus($voucherId);
    echo "IsTest?: " . ($statusRes['isTest'] ? "true" : "false") . "\n";

    // 4. Bulk Generation (Create 10 vouchers worth 25 LYD instantly)
    $bulkRes = $client->bulkIssueVouchers(25.0, 10);
    echo "Bulk Created Vouchers: " . count($bulkRes['vouchers']) . "\n";

    // 5. Void when complete
    $client->voidVoucher($voucherId);
    echo "Voucher voided successfully.\n";

} catch (APIError $e) {
    echo "API Request failed (" . $e->getStatusCode() . "): " . $e->getMessage() . "\n";
    print_r($e->getResponseBody());
}
```

## Laravel Framework Usage

Because we ship a native service provider hook (`Commerce\Vouchers\Laravel\VouchersServiceProvider`), the package instantly loads your `.env` keys mapping natively.

Simply add the keys to your Application's `.env` configuration file:

```env
VOUCHERS_API_KEY_ID="your_api_key"
VOUCHERS_API_SECRET="your_api_secret"
VOUCHERS_BASE_URL="https://api.wavecommerce.ly"
# VOUCHERS_VERIFY_SSL=false # For local sandbox development bridging HTTPS
```

Anywhere in your Laravel controllers, directly fetch from the IoC Facade or via dependency injection:

```php
namespace App\Http\Controllers;

use Commerce\Vouchers\Laravel\VouchersFacade as Vouchers;

class TopupController extends Controller 
{
    public function generate() 
    {
        $payload = Vouchers::issueVoucher(2400.0);
        
        return response()->json([
            'voucherCode' => $payload['voucher']['code']
        ]);
    }
}
```