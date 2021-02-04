<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';


if (! isset($_GET['id'])) {
    exit(1);
}

$invoice = Capsule::table('tblinvoices')->where('id', $_GET['id'])->first();

header('Content-Type', 'application/json');

echo json_encode([
    'status' => $invoice->status,
]);
