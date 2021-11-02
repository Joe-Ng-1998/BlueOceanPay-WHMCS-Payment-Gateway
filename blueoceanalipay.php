<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/blueoceanpay/BlueOceanPay.php';

function blueoceanalipay_MetaData()
{
    return [
        'DisplayName' => 'Alipay via BlueOcean',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function blueoceanalipay_config()
{
    return (new BlueOceanPay)->config('Alipay');
}

function blueoceanalipay_link(array $parameters)
{
    $systemUrl = $parameters['systemurl'];

    $response = (new BlueOceanPay($parameters['appid'], $parameters['key']))->pay([
        'payment' => 'alipay.wappay',
        'out_trade_no' => $parameters['invoiceid'] . '-' . time(),
        'total_fee' => $parameters['amount'],
        'wallet' => 'CN',
        'notify_url' => $systemUrl . '/modules/gateways/callback/blueoceanpay.php',
    ]);

    $invoiceStatus = $systemUrl. '/modules/gateways/blueoceanpay/invoice_status.php?id=' . $parameters['invoiceid'];

    if ($response['message'] !== 'success') {
        return '<span style="color:red;">'. $response['message'] .'</red>';
    }

    return <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <div style="display:flex; flex-direction: column; align-items: center;">
        <p>
            <img src="https://ac.alipay.com/storage/2020/6/4/5f7a45a1-3398-4029-8791-a9545a496642.svg" style="height: 25px">
        </p>
        <div id="qrcode"></div>
    </div>
    <script type="text/javascript">
        const qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "{$response['data']['qrcode']}",
            width: 300,
            height: 300,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        setInterval(() => {
            fetch("{$invoiceStatus}")
                .then(r => r.json())
                .then(r => {
                    if (r.status === 'Paid') {
                        window.location.reload(true)
                    }
                })
        }, 1000);
    </script>
HTML;
}

function blueoceanalipay_refund(array $parameters) {
    $response =  (new BlueOceanPay($parameters['appid'], $parameters['key']))->refund([
        'sn' => $parameters['transid'],
        'refund_fee' => $parameters['amount'],
    ]);

    return [
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $response['code'] == 200 ? 'success' : 'error',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $response,
        // Unique Transaction ID for the refund transaction
        'transid' => $response['data']['out_refund_no'],
    ];
}
