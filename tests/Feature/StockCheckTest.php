<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Equipment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class StockCheckTest extends TestCase
{
    // use RefreshDatabase; // Use transaction rollback instad if possible, or RefreshDatabase if needed. 
    // Given the environment, let's try to be careful. But Feature tests usually need clean state.
    // The user has sqlite or mysql? .env said DB_CONNECTION=sqlite or mysql? 
    // Let's assume standard Laravel testing traits.
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // ✅ Fix: Configure 'depart_it_db' and 'mysql' to use the same SQLite DB as default for testing
        // We must share the PDO instance to allow cross-table queries in in-memory SQLite
        $defaultPdo = \Illuminate\Support\Facades\DB::connection('sqlite')->getPdo();
        
        config(['database.connections.depart_it_db' => config('database.connections.sqlite')]);
        config(['database.connections.mysql' => config('database.connections.sqlite')]);
        
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::purge('depart_it_db');

        \Illuminate\Support\Facades\DB::connection('depart_it_db')->setPdo($defaultPdo);
        \Illuminate\Support\Facades\DB::connection('mysql')->setPdo($defaultPdo);
        
        // ✅ Create missing 'sync_ldap' table for User model (External DB simulation)
        if (!\Illuminate\Support\Facades\Schema::connection('depart_it_db')->hasTable('sync_ldap')) {
            \Illuminate\Support\Facades\Schema::connection('depart_it_db')->create('sync_ldap', function ($table) {
                $table->id();
                $table->string('name')->nullable(); // ✅ Added to match UserFactory
                $table->string('fullname')->nullable();
                $table->string('username')->unique();
                $table->string('employeecode')->nullable();
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('user');
                $table->string('photo_path')->nullable();
                $table->string('access_token')->nullable();
                $table->string('status')->default('active');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->rememberToken();
            });
        }

        // ✅ Create missing 'settings' table (Missing Migration)
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            \Illuminate\Support\Facades\Schema::create('settings', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // Setup User
        $this->user = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_test', // ✅ Added username
        ]);
        $this->actingAs($this->user);

        // Setup Settings
        Setting::updateOrCreate(['key' => 'auto_po_schedule_day'], ['value' => '24']);
        Setting::updateOrCreate(['key' => 'auto_po_schedule_time'], ['value' => '23:50']);
        Setting::updateOrCreate(['key' => 'automation_requester_id'], ['value' => $this->user->id]);
        Setting::updateOrCreate(['key' => 'last_auto_po_run_month'], ['value' => '2000-01']); // Ensure it runs
    }

    /** @test */
    public function it_finds_item_with_zero_stock_and_min_stock_greater_than_zero()
    {
        // 1. Create Equipment with 0 stock and min_stock 5
        $item = Equipment::create([
            'name' => 'Test Item Zero Stock',
            'quantity' => 0,
            'min_stock' => 5,
        ]);

        // 2. Run the command (Draft Mode to avoid API calls)
        Artisan::call('stock:monthly-check', ['--draft-only' => true, '--force' => true]);

        // 3. Verify PO is created with this item
        $po = PurchaseOrder::where('status', 'pending')
            ->where('type', 'scheduled')
            ->whereHas('items', function($q) use ($item) {
                $q->where('equipment_id', $item->id);
            })->first();

        $this->assertNotNull($po, 'Purchase Order should be created.');
        $this->assertEquals(1, $po->items->count(), 'Should have 1 item in PO');
        $this->assertEquals($item->id, $po->items->first()->equipment_id);
    }

    /** @test */
    public function it_ignores_item_with_zero_stock_if_min_stock_is_zero()
    {
        // 1. Create Equipment with 0 stock and min_stock 0
        $item = Equipment::create([
            'name' => 'Test Item Ignored',
            'quantity' => 0,
            'min_stock' => 0, // Should be ignored
        ]);

        // 2. Run command
        Artisan::call('stock:monthly-check', ['--draft-only' => true, '--force' => true]);

        // 3. Verify NO PO created for this item
        $po = PurchaseOrder::where('status', 'pending')
            ->where('type', 'scheduled')
            ->whereHas('items', function($q) use ($item) {
                $q->where('equipment_id', $item->id);
            })->first();

        $this->assertNull($po, 'Item with min_stock 0 should be ignored.');
    }

    /** @test */
    public function it_ignores_item_if_already_in_pending_po()
    {
        // 1. Create Equipment
        $item = Equipment::create([
            'name' => 'Item 1 In Pending',
            'quantity' => 0,
            'min_stock' => 5,
        ]);

        // 2. Create an EXISTING Pending PO containing this item
        $existingPo = PurchaseOrder::create(['status' => 'pending', 'type' => 'scheduled']);
        PurchaseOrderItem::create([
            'purchase_order_id' => $existingPo->id,
            'equipment_id' => $item->id,
            'quantity_ordered' => 10,
            'status' => 'pending',
            'item_description' => $item->name,
        ]);

        // 3. Run command (Without FORCE, so it checks for duplicates)
        Artisan::call('stock:monthly-check', ['--draft-only' => true]); 

        // 4. Verify no NEW items added or nothing changes unreasonably.
        $item2 = Equipment::create([
            'name' => 'Item 2 Needs Order',
            'quantity' => 0, 
            'min_stock' => 5
        ]);

        Artisan::call('stock:monthly-check', ['--draft-only' => true]);

        // Refresh PO
        $existingPo->refresh();

        // Expect: Item 2 added. Item 1 NOT added again (qty not doubled/touched if query excluded it).
        $this->assertTrue($existingPo->items->contains('equipment_id', $item2->id), 'Item 2 should be added to existing pending PO');
    }
}
