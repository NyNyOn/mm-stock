<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Equipment;
use App\Models\Transaction;
use App\Services\PuHubService;
use Illuminate\Support\Facades\Http;

class PuHubIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ 1. Force all connections to use SQLite Driver/Grammar
        // This prevents MySQL-specific SQL (like 'update table set table.col') from breaking SQLite
        $sqliteConfig = config('database.connections.sqlite');
        config(['database.connections.depart_it_db' => $sqliteConfig]);
        config(['database.connections.mysql' => $sqliteConfig]);

        // ✅ 2. Share the SAME PDO instance across all connections
        // This ensures they all talk to the same :memory: database created by RefreshDatabase
        $pdo = \Illuminate\Support\Facades\DB::connection('sqlite')->getPdo();
        \Illuminate\Support\Facades\DB::connection('depart_it_db')->setPdo($pdo);
        \Illuminate\Support\Facades\DB::connection('mysql')->setPdo($pdo);
        
        // Mock HTTP สำหรับ PU-HUB (จับทุก request เพื่อความชัวร์)
        Http::fake([
            '*' => Http::response(['status' => 'success', 'pr_id' => 999, 'pr_code' => 'PR-AUTO-001'], 200)
        ]);

        // ✅ Create Mock User Table (sync_ldap)
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');
        if (!$schema->hasTable('sync_ldap')) {
             $schema->create('sync_ldap', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('fullname')->nullable();
                $table->string('username')->nullable();
                $table->string('employeecode')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('access_token')->nullable();
                $table->string('status')->nullable();
                $table->string('department_id')->nullable();
            });
        }

        // สร้าง Admin User
        $this->admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@test.com',
            'username' => 'admin', // Added username as it might be required by seeder/logic
            'department_id' => '1'
        ]);
        
        // สร้าง Equipment
        $this->equipment = Equipment::create([
            'name' => 'Test Mouse',
            'quantity' => 100,
            'withdrawal_type' => 'consumable',
            'status' => 'available'
        ]);

        // ✅ Bypass Permission Check for Tests
        \Illuminate\Support\Facades\Gate::before(function () {
            return true;
        });
    }

    /**
     * Test: รับ webhook จาก PU-HUB ว่าของมาถึง (Phase 2)
     */
    public function test_receive_hub_notification_updates_po_status()
    {
        // Arrange: สร้าง PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'ordered_by_user_id' => $this->admin->id,
            'status' => 'ordered',
            'type' => 'urgent'
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'item_description' => 'Test Mouse',
            'quantity_ordered' => 10,
            'status' => 'ordered'
        ]);

        // Act: ส่ง webhook notification
        // Note: URL might need adjustment based on route:list output
        $response = $this->postJson('/api/v1/notify-hub-arrival', [
            'pr_item_id' => $poItem->id,
            'po_code' => 'PO-TEST-001',
            'status' => 'arrived_at_hub'
        ]);

        // DEBUG: If 405, it means metod/route wrong. If 500, check log.
        if ($response->status() !== 200) {
            dump($response->status());
            dump($response->json());
        }

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $po->refresh();
        $this->assertEquals('shipped_from_supplier', $po->status);
    }

    /**
     * Test: รับของเข้าสต็อก - ของดีทั้งหมด
     */
    public function test_receive_items_updates_stock_and_sends_to_puhub()
    {
        // Arrange
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-002',
            'ordered_by_user_id' => $this->admin->id,
            'status' => 'shipped_from_supplier',
            'type' => 'urgent'
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'item_description' => 'Test Mouse',
            'quantity_ordered' => 10,
            'status' => 'ordered'
        ]);

        $initialStock = $this->equipment->quantity;

        // Act: รับของ
        $response = $this->actingAs($this->admin)
            ->post(route('receive.process'), [
                'items' => [
                    $poItem->id => [
                        'selected' => true,
                        'receive_now_quantity' => 10,
                        'inspection_status' => 'pass',
                        'inspection_notes' => 'Perfect condition'
                    ]
                ]
            ]);

        // DEBUG Redirect
        if ($response->status() === 302 && $response->headers->get('Location') !== route('receive.index')) {
             dump('Redirected to: ' . $response->headers->get('Location'));
             dump('Session: ', session()->all());
        }

        // Assert
        $response->assertRedirect(route('receive.index'));
        $response->assertSessionHas('success');

        // เช็คว่าสต็อกเพิ่มขึ้น
        $this->equipment->refresh();
        $this->assertEquals($initialStock + 10, $this->equipment->quantity);

        // เช็คว่ามี Transaction log
        $this->assertDatabaseHas('transactions', [
            'equipment_id' => $this->equipment->id,
            'type' => 'receive',
            'quantity_change' => 10
        ]);

        // เช็คว่า PO Item ถูก update
        $poItem->refresh();
        $this->assertEquals(10, $poItem->quantity_received);
        $this->assertEquals('pass', $poItem->inspection_status);
    }

    /**
     * Test: รับของบางส่วน - ของมาไม่ครบ
     */
    public function test_receive_partial_items()
    {
        // Arrange
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-003',
            'ordered_by_user_id' => $this->admin->id,
            'status' => 'shipped_from_supplier',
            'type' => 'urgent'
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'item_description' => 'Test Mouse',
            'quantity_ordered' => 10,
            'status' => 'ordered'
        ]);

        $initialStock = $this->equipment->quantity;

        // Act: รับของแค่ 5 ชิ้น (มาไม่ครบ)
        $response = $this->actingAs($this->admin)
            ->post(route('receive.process'), [
                'items' => [
                    $poItem->id => [
                        'selected' => true,
                        'receive_now_quantity' => 5,
                        'inspection_status' => 'incomplete',
                        'inspection_notes' => 'Short shipment - only 5 arrived'
                    ]
                ]
            ]);

        // Assert
        $response->assertRedirect(route('receive.index'));

        // เช็คว่าสต็อกเพิ่มเท่าที่รับจริง
        $this->equipment->refresh();
        $this->assertEquals($initialStock + 5, $this->equipment->quantity);

        $poItem->refresh();
        $this->assertEquals(5, $poItem->quantity_received);
        $this->assertEquals('incomplete', $poItem->inspection_status);
        
        // PO ควรเป็น partial_receive
        $po->refresh();
        $this->assertEquals('partial_receive', $po->status);
    }

    /**
     * Test: ปฏิเสธของเสีย - ไม่เพิ่มสต็อก
     */
    public function test_reject_damaged_items_does_not_increase_stock()
    {
        // Arrange
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-004',
            'ordered_by_user_id' => $this->admin->id,
            'status' => 'shipped_from_supplier',
            'type' => 'urgent'
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'item_description' => 'Test Mouse',
            'quantity_ordered' => 10,
            'status' => 'ordered'
        ]);

        $initialStock = $this->equipment->quantity;

        // Act: ปฏิเสธของ (ของเสียหมด)
        $response = $this->actingAs($this->admin)
            ->post(route('receive.process'), [
                'items' => [
                    $poItem->id => [
                        'selected' => true,
                        'receive_now_quantity' => 10,
                        'inspection_status' => 'damaged',
                        'inspection_notes' => 'All units broken during transit'
                    ]
                ]
            ]);

        // Assert
        $response->assertRedirect(route('receive.index'));

        // สต็อกไม่เพิ่ม
        $this->equipment->refresh();
        $this->assertEquals($initialStock, $this->equipment->quantity);

        // แต่มี Transaction log (quantity_change = 0) เป็นหลักฐาน
        $this->assertDatabaseHas('transactions', [
            'equipment_id' => $this->equipment->id,
            'type' => 'receive',
            'quantity_change' => 0
        ]);

        $poItem->refresh();
        $this->assertEquals('damaged', $poItem->inspection_status);
        $this->assertEquals('inspection_failed', $poItem->status);
    }

    /**
     * Test: PuHubService ส่ง PR สำเร็จ
     */
    public function test_create_purchase_request_via_service()
    {
        // Arrange
        Http::fake([
            '*pu-hub*' => Http::response([
                'status' => 'success',
                'pr_id' => 999,
                'pr_code' => 'PR-AUTO-001'
            ], 201)
        ]);

        $service = app(PuHubService::class);

        // Act
        $result = $service->createPurchaseRequest([
            'requestor_user_id' => $this->admin->id,
            'origin_department_id' => 1,
            'priority' => 'Urgent',
            'items' => [
                [
                    'item_name_custom' => 'Test Mouse',
                    'quantity' => 50,
                    'unit_name' => 'pcs',
                    'notes' => 'Low stock alert'
                ]
            ]
        ]);

        // Assert
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(999, $result['pr_id']);
        $this->assertEquals('PR-AUTO-001', $result['pr_code']);
    }
}
