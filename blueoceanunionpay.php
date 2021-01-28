<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require __DIR__ . '/blueoceanpay/BlueOceanPay.php';

function blueoceanunionpay_MetaData()
{
    return array(
        'DisplayName' => 'UnionPay via BlueOcean',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function blueoceanunionpay_config()
{
    return (new BlueOceanPay)->config();
}

function blueoceanunionpay_link(array $parameters)
{
    $systemUrl = $parameters['systemurl'];

    $response = (new BlueOceanPay($parameters['appid'], $parameters['key']))->pay([
        'payment' => 'unionpay.link',
        'out_trade_no' => $parameters['invoiceid'] . '-' . time(),
        'total_fee' => $parameters['amount'],
        'wallet' => 'CN',
        'notify_url' => $systemUrl . '/modules/gateways/callback/blueoceanpay.php',
    ]);

    if ($response['message'] !== 'success') {
        return '<span style="color:red;">'. $response['message'] .'</red>';
    }

    return <<<HTML
    <a href="{$response['data']['qrcode']}">
        <button type="button" style="
            height: 44px;
            width: 100px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
        "><img src="https://upload.wikimedia.org/wikipedia/commons/1/1b/UnionPay_logo.svg" style="width: 36px;
            height: 30px;
            margin-right: 8px;"><span style="
            /* height: fit-content; */
            display: flex;
            justify-content: center;
            align-items: center;
        ">Pay</span></button>
    </a>
HTML;
}

function blueoceanunionpay_refund(array $parameters) {
    $response =  (new BlueOceanPay($parameters['appid'], $parameters['key']))->refund([
        'sn' => $parameters['transid'],
        'refund_fee' => $parameters['amount'] - 0.01, // 减掉多加的
    ]);

    return [
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $response['message'] === 'SUCCESS' ? 'success' : 'error',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $response,
        // Unique Transaction ID for the refund transaction
        'transid' => $response['data']['out_refund_no'],
    ];
}
