<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Equipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class PoSplittingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup SQLite environment just like PuHubIntegrationTest
        $sqliteConfig = config('database.connections.sqlite');
        config(['database.connections.depart_it_db' => $sqliteConfig]);
        config(['database.connections.mysql' => $sqliteConfig]);

        $pdo = \Illuminate\Support\Facades\DB::connection('sqlite')->getPdo();
        \Illuminate\Support\Facades\DB::connection('depart_it_db')->setPdo($pdo);
        \Illuminate\Support\Facades\DB::connection('mysql')->setPdo($pdo);

        // Fake Outgoing Requests to prevent errors
        Http::fake();

        // Create Permissions Tables if missing (Mocking structure)
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');
        if (!$schema->hasTable('sync_ldap')) {
             $schema->create('sync_ldap', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('username')->nullable();
                $table->string('fullname')->nullable();
                $table->string('employeecode')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('access_token')->nullable();
                $table->string('status')->nullable();
                $table->string('department_id')->nullable();
            });
        }

        if (!$schema->hasTable('settings')) {
            $schema->create('settings', function ($table) {
               $table->id();
               $table->string('key')->unique();
               $table->text('value')->nullable();
               $table->timestamps();
           });
       }
        
        // Mock Admin
        $this->admin = User::factory()->create(['name' => 'admin', 'username' => 'admin']);
        
        // Mock Equipment
        $this->equipment = Equipment::create(['name' => 'Test Item', 'quantity' => 0]);
        
        // Mock Gate
        \Illuminate\Support\Facades\Gate::before(fn() => true);
    }

    public function test_items_split_and_merge_into_same_po()
    {
        // 1. Create a Local PO (acting as the initial PR container)
        // No PO Number yet, mimicking "PR Issued" state
        $po = PurchaseOrder::create([
            'pr_number' => 'PR-TEST-001',
            'po_number' => null, // Important: No PO number initially
            'ordered_by_user_id' => $this->admin->id,
            'status' => 'ordered'
        ]);

        // Add 2 Items
        $item1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'pr_item_id' => 101, // ID from PU
            'quantity_ordered' => 1,
            'status' => 'pending'
        ]);

        $item2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'equipment_id' => $this->equipment->id,
            'pr_item_id' => 102, // ID from PU
            'quantity_ordered' => 1,
            'status' => 'pending'
        ]);

        // Verify Initial State
        $this->assertEquals($po->id, $item1->purchase_order_id);
        $this->assertEquals($po->id, $item2->purchase_order_id);
        $this->assertCount(2, $po->items);

        // 2. Action: Receive Update for Item 1 -> Assigned to 'PO-REAL-A'
        // This triggers the first split.
        Sanctum::actingAs($this->admin); // Authenticate for API

        $response1 = $this->postJson(route('api.v1.notify-hub-arrival'), [
            'event' => 'item_inspection_result',
            'action' => 'recheck', // Neutral action that shouldn't auto-complete
            'pr_item_id' => 101,
            'po_code' => 'PO-REAL-A', // New PO Number
            'inspector' => 'Mr. Tester'
        ]);

        $response1->assertStatus(200);

        // Refresh Items
        $item1->refresh();
        $item2->refresh();

        // 3. Assert: Item 1 should be moved to a NEW PO
        $this->assertNotEquals($po->id, $item1->purchase_order_id, "Item 1 should have moved");
        $newPoId = $item1->purchase_order_id;
        $newPo = PurchaseOrder::find($newPoId);
        
        $this->assertEquals('PO-REAL-A', $newPo->po_number);
        // recheck action updates status to shipped_from_supplier if it was ordered
        $this->assertEquals('shipped_from_supplier', $newPo->status);
        
        // Assert: Item 2 should still be in the Original PO (or at least NOT in the new one yet)
        $this->assertEquals($po->id, $item2->purchase_order_id, "Item 2 should stay in original PO");


        // 4. Action: Receive Update for Item 2 -> ALSO Assigned to 'PO-REAL-A'
        // This should trigger the MERGE logic into the EXISTING 'PO-REAL-A'
        $response2 = $this->postJson(route('api.v1.notify-hub-arrival'), [
            'event' => 'item_inspection_result',
            'action' => 'recheck',
            'pr_item_id' => 102,
            'po_code' => 'PO-REAL-A', // SAME PO Number
            'inspector' => 'Mr. Tester'
        ]);

        $response2->assertStatus(200);

        // Refresh Items
        $item1->refresh();
        $item2->refresh();

        // 5. Assert: Item 2 should be moved to the SAME New PO as Item 1
        $this->assertEquals($newPoId, $item2->purchase_order_id, "Item 2 should merge into the existing target PO");
        $this->assertEquals('PO-REAL-A', $item2->purchaseOrder->po_number);

        // Verify that we didn't create a *third* PO
        $totalPOs = PurchaseOrder::where('po_number', 'PO-REAL-A')->count();
        $this->assertEquals(1, $totalPOs, "There should be exactly one PO with number PO-REAL-A");
        
        // Optional: The original PO might be empty now.
        $po->refresh();
        $this->assertCount(0, $po->items);
    }
}
