<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$baseUrl = 'http://192.168.10.131';
$candidates = [
    '/api/v1/notify-hub-arrival',
    '/api/v1/purchase-orders/notify-arrival',
    '/api/v1/purchase-orders/arrival-notification',
    '/api/v1/pr-items/notify-arrival',
    '/api/v1/notify-arrival',
    '/api/v1/arrival-notification',
    '/api/v1/receive-notification',
];

echo "Probing PU-HUB at $baseUrl...\n";

foreach ($candidates as $path) {
    echo "Checking: $path ... ";
    try {
        $response = Http::timeout(2)->post($baseUrl . $path, []);
        $status = $response->status();
        echo "Status: $status\n";
        
        if ($status != 404 && $status != 405) {
            echo "--> POTENTIAL MATCH FOUND! <--\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
