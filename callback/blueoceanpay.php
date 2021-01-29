<?php

use WHMCS\Database\Capsule;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';


$payload = array_merge($_GET, $_POST, $_REQUEST);

$gateways = [
    'LINK' => 'blueoceanunionpay',
];

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gateways[$payload['trade_type']]);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

if ($payload['trade_state'] === 'SUCCESS') {
    $invoiceId = explode('-', $payload['out_trade_no'])[0];
    checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($payload['sn']);

    logTransaction($gatewayParams['name'], $_POST, $payload['trade_state']);

    if (! class_exists('BlueOceanPay')) {
        require __DIR__ . '/../blueoceanpay/BlueOceanPay.php';
    }

    $amount = (new BlueOceanPay($gatewayParams['appid'], $gatewayParams['key']))->convertToUSD($payload['total_fee'] / 100);
    $amount = round($amount, 2);

    $invoice = Capsule::table('tblinvoices')->find($invoiceId);

    // 允许误差范围 0.05
    $shouldPay = (float) $invoice->total;
    if ($shouldPay > $amount && ((($shouldPay - $amount) / $shouldPay) <= 0.05 || ($shouldPay - $amount) <= 0.05)) {
        $amount += $shouldPay - $amount;
    }

    addInvoicePayment(
        $invoiceId,
        $payload['sn'],
        $amount,
        $shouldPay > $amount ? $shouldPay - $amount : 0.00,
        $gateways[$payload['trade_type']],
    );
}

echo 'SUCCESS';
