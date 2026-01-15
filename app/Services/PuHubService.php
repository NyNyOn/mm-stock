<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PuHubService
{
    protected string $baseUrl;
    protected string $apiToken;

    public function __construct()
    {
        $this->baseUrl = \App\Models\Setting::where('key', 'pu_api_base_url')->value('value') ?? config('services.pu_hub.base_url') ?? '';
        $this->apiToken = \App\Models\Setting::where('key', 'pu_api_token')->value('value') ?? config('services.pu_hub.token') ?? '';
    }

    /**
     * ส่งผลการตรวจสอบของกลับไปยัง PU-HUB (Phase 3)
     * 
     * @param array $inspections รายการตรวจสอบ
     * @return array Response from PU-HUB
     * @throws \Exception
     */
    public function confirmInspectionBatch(array $inspections): array
    {
        Log::info('[PuHub] Sending inspection batch to PU-HUB', [
            'count' => count($inspections),
            'inspections' => $inspections
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/api/v1/pr-items/confirm-inspection-batch', [
                'inspections' => $inspections
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('[PuHub] Successfully sent inspection batch', $data);
                return $data;
            }

            Log::error('[PuHub] Failed to send inspection batch', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('PU-HUB API Error: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('[PuHub] Exception sending inspection batch: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * สร้าง Purchase Request ใหม่ไปยัง PU-HUB (Phase 1)
     * 
     * @param array $data PR data
     * @return array Response from PU-HUB
     */
    public function createPurchaseRequest(array $data): array
    {
        Log::info('[PuHub] Creating new PR in PU-HUB', $data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/api/v1/purchase-requests/intake', $data);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('[PuHub] Successfully created PR', [
                    'pr_id' => $result['pr_id'] ?? null,
                    'pr_code' => $result['pr_code'] ?? null
                ]);
                return $result;
            }

            Log::error('[PuHub] Failed to create PR', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('PU-HUB API Error: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('[PuHub] Exception creating PR: ' . $e->getMessage());
            throw $e;
        }
    }
}
