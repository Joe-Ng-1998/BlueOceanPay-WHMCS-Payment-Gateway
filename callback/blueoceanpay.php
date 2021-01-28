<?php

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
// include __DIR__ . '/../blueoceanpay/BlueOceanPay.php';

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

    $amount = (new BlueOceanPay($gatewayParams['appid'], $gatewayParams['key']))->convertToUSD($payload['total_fee'] / 100);
    $amount = round($amount, 2) + 0.01;

    addInvoicePayment(
        $invoiceId,
        $payload['sn'],
        $amount,
        0.01,
        $gateways[$payload['trade_type']],
    );
}

echo 'SUCCESS';
