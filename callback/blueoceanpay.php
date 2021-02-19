<?php

use WHMCS\Database\Capsule;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';


$payload = array_merge($_GET, $_POST, $_REQUEST);

$gateways = [
    'LINK' => 'blueoceanunionpay',
    'WAPPAY' => 'blueoceanalipay',
];

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gateways[$payload['trade_type']]);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// 锁配置信息.
$lockDir = __DIR__ . '/blueocean-locks';
$lockFile = $lockDir . '/' . 'blueocean.sn.' . $payload['sn'] . '.lock';

if (! is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}

// 检查是否有锁.
if (file_exists($lockFile)) {
    echo 'fail';die;
} else {
    file_put_contents($lockFile, microtime());
}

if ($payload['trade_state'] === 'SUCCESS') {
    $invoiceId = explode('-', $payload['out_trade_no'])[0];
    checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($payload['sn']);

    logTransaction($gatewayParams['name'], $_POST, $payload['trade_state']);

    if (! class_exists('BlueOceanPay')) {
        require_once __DIR__ . '/../blueoceanpay/BlueOceanPay.php';
    }

    $amount = (new BlueOceanPay($gatewayParams['appid'], $gatewayParams['key']))->convertToUSD($payload['total_fee'] / 100);
    $amount = round($amount, 2);

    $invoice = Capsule::table('tblinvoices')->find($invoiceId);

    $shouldPay = (float) $invoice->total;
    $diff = 0;
    // 允许误差范围 0.05
    if ($shouldPay > $amount && ((($shouldPay - $amount) / $shouldPay) <= 0.05 || ($shouldPay - $amount) <= 0.05)) {
        $amount += $diff = $shouldPay - $amount;
    }

    addInvoicePayment(
        $invoiceId,
        $payload['sn'],
        $amount,
        $amount * ($gatewayParams['transaction_fee'] / 100) + $diff,
        $gateways[$payload['trade_type']],
    );
}

// 解除锁.
unlink($lockFile);

echo 'success';
