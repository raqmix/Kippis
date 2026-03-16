<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$merchantId = config('mastercard.merchant_id');
$apiUsername = config('mastercard.api_username') ?: $merchantId;
$apiPassword = config('mastercard.api_password');
$base = rtrim(config('mastercard.gateway'), '/');
$version = config('mastercard.api_version');

$sessionId = $argv[1] ?? 'SESSION0002019401185E82561426L4';
$url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/session/{$sessionId}";

$client = new \GuzzleHttp\Client();
$res = $client->get($url, ['auth' => [$apiUsername, $apiPassword]]);
echo $res->getBody()->getContents() . "\n";
