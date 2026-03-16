<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$merchantId = config('mastercard.merchant_id');
$apiUsername = config('mastercard.api_username') ?: $merchantId;
$apiPassword = config('mastercard.api_password');
$base = rtrim(config('mastercard.gateway'), '/');
$version = config('mastercard.api_version');

$url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/session";
$payload = [
    'apiOperation' => 'CREATE_CHECKOUT_SESSION',
    'order' => [
        'id' => 'test_' . time(),
        'amount' => '10.00',
        'currency' => 'EGP'
    ]
];

$client = new \GuzzleHttp\Client();
try {
$res = $client->post($url, ['auth' => [$apiUsername, $apiPassword], 'json' => $payload]);
echo $res->getBody()->getContents() . "\n";
} catch (\Exception $e) { echo $e->getResponse()->getBody() . "\n"; }
