<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PuHubService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class PuHubServiceTest extends TestCase
{
    protected PuHubService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('puhub.base_url', 'https://pu-hub-test.example.com');
        Config::set('puhub.api_token', 'test-token-123');
        
        $this->service = new PuHubService();
    }

    /**
     * Test ส่งผลการตรวจสอบสำเร็จ
     */
    public function test_confirm_inspection_batch_success()
    {
        // Arrange: Mock HTTP Response
        Http::fake([
            'pu-hub-test.example.com/*' => Http::response([
                'message' => 'Bulk inspection processed.',
                'results' => [
                    'success' => [501, 502],
                    'failed' => []
                ]
            ], 200)
        ]);

        $inspections = [
            [
                'pr_item_id' => 501,
                'status' => 'accepted',
                'received_quantity' => 10,
                'notes' => 'Perfect condition'
            ],
            [
                'pr_item_id' => 502,
                'status' => 'rejected',
                'received_quantity' => 5,
                'notes' => 'Damaged'
            ]
        ];

        // Act
        $result = $this->service->confirmInspectionBatch($inspections);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Bulk inspection processed.', $result['message']);
        $this->assertCount(2, $result['results']['success']);
        
        // Verify HTTP Request
        Http::assertSent(function ($request) {
            return $request->url() == 'https://pu-hub-test.example.com/api/v1/pr-items/confirm-inspection-batch'
                && $request->hasHeader('Authorization', 'Bearer test-token-123')
                && count($request['inspections']) === 2;
        });
    }

    /**
     * Test ส่งผลการตรวจสอบล้มเหลว (API Error)
     */
    public function test_confirm_inspection_batch_api_error()
    {
        // Arrange
        Http::fake([
            'pu-hub-test.example.com/*' => Http::response([], 500)
        ]);

        $inspections = [
            ['pr_item_id' => 501, 'status' => 'accepted', 'received_quantity' => 10, 'notes' => '']
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PU-HUB API Error: 500');
        
        $this->service->confirmInspectionBatch($inspections);
    }

    /**
     * Test สร้าง Purchase Request สำเร็จ
     */
    public function test_create_purchase_request_success()
    {
        // Arrange
        Http::fake([
            'pu-hub-test.example.com/*' => Http::response([
                'status' => 'success',
                'pr_id' => 250,
                'pr_code' => 'PR-20260113-0001'
            ], 201)
        ]);

        $prData = [
            'requestor_user_id' => 15,
            'origin_department_id' => 1,
            'priority' => 'Scheduled',
            'items' => [
                [
                    'item_name_custom' => 'Dell Monitor 24-inch',
                    'quantity' => 10,
                    'unit_name' => 'set',
                    'notes' => 'For new staff'
                ]
            ]
        ];

        // Act
        $result = $this->service->createPurchaseRequest($prData);

        // Assert
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(250, $result['pr_id']);
        $this->assertEquals('PR-20260113-0001', $result['pr_code']);
    }
}
