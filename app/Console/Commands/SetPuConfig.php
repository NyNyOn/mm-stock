<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;

class SetPuConfig extends Command
{
    protected $signature = 'pu:config {key} {value}';
    protected $description = 'Set PU API Configuration';

    public function handle()
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        
        // Map friendly names to DB keys
        $map = [
            'arrival_url' => 'pu_api_arrival_path',
            'base_url' => 'pu_api_base_url',
            'token' => 'pu_api_token'
        ];
        
        $dbKey = $map[$key] ?? $key;

        Setting::updateOrCreate(
            ['key' => $dbKey],
            ['value' => $value]
        );

        $this->info("Updated '$dbKey' to '$value'");
    }
}
