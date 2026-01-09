<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "Checking Synology Configuration...\n";
$webhookUrl = Config::get('services.synology.chat_webhook_url');

if ($webhookUrl) {
    echo "Webhook URL Found: " . substr($webhookUrl, 0, 20) . "...\n";
    
    echo "Attempting to send test message...\n";
    $payload = ['text' => "🔔 Debug Test Message from MM Stock System"];
    
    try {
        $response = Http::withoutVerifying()->asForm()->post($webhookUrl, ['payload' => json_encode($payload)]);
        
        if ($response->successful()) {
            echo "✅ Notification Sent Successfully!\n";
        } else {
            echo "❌ Failed to send notification. Status: " . $response->status() . "\n";
            echo "Response: " . $response->body() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }

} else {
    echo "❌ Webhook URL is NOT set in config/services.php or .env\n";
    echo "Current services.synology config: " . json_encode(Config::get('services.synology')) . "\n";
}
