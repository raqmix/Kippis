<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = new \GuzzleHttp\Client();
$auth = ['auth' => ['Merchant.TestCaeMer97', '44dac46dbc37e010d79ff76689943481']];
$base = 'https://test-gateway.mastercard.com/api/rest/version/70/merchant/TestCAEMER97';

$res = $client->post("$base/session", array_merge($auth, ['json' => [
    'apiOperation' => 'INITIATE_CHECKOUT',
    'order' => [ 'id' => 'ver_'.time(), 'amount' => '10.00', 'currency' => 'EGP' ],
    'interaction' => [ 'operation' => 'VERIFY', 'returnUrl' => 'http://test' ]
]]));
$session = json_decode($res->getBody()->getContents(), true);
$sessionId = $session['session']['id'];
echo "SESSION (VERIFY): $sessionId\n";

try {
$payRes = $client->put("$base/order/ord_".time()."/transaction/pay_1", array_merge($auth, ['json' => [
    'apiOperation' => 'PAY',
    'order' => [ 'amount' => '10.00', 'currency' => 'EGP' ],
    'session' => [ 'id' => $sessionId ],
    'sourceOfFunds' => [ 'type' => 'CARD' ]
]]));
echo "PAY SUCCESS: " . $payRes->getBody()->getContents() . "\n";
} catch (\Exception $e) { echo "PAY ERROR: " . $e->getResponse()->getBody() . "\n"; }

echo "-----\n";

$res2 = $client->post("$base/session", array_merge($auth, ['json' => [
    'apiOperation' => 'INITIATE_CHECKOUT',
    'order' => [ 'id' => 'none_'.time(), 'amount' => '10.00', 'currency' => 'EGP' ],
    'interaction' => [ 'operation' => 'NONE', 'returnUrl' => 'http://test' ]
]]));
$session2 = json_decode($res2->getBody()->getContents(), true);
$sessionId2 = $session2['session']['id'];
echo "SESSION (NONE): $sessionId2\n";

try {
$payRes2 = $client->put("$base/order/ord_".time()."/transaction/pay_1", array_merge($auth, ['json' => [
    'apiOperation' => 'PAY',
    'order' => [ 'amount' => '10.00', 'currency' => 'EGP' ],
    'session' => [ 'id' => $sessionId2 ],
    'sourceOfFunds' => [ 'type' => 'CARD' ]
]]));
echo "PAY SUCCESS: " . $payRes2->getBody()->getContents() . "\n";
} catch (\Exception $e) { echo "PAY ERROR: " . $e->getResponse()->getBody() . "\n"; }

