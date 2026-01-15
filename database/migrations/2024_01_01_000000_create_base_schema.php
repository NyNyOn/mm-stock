<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 0. Aux Tables (Categories, Locations, Units) - Needed for Foreign Keys
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('prefix')->nullable(); // ✅ Added prefix
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        // 1. Create Sessions Table
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // 2. Create Equipments Table (Base Structure Only)
        if (!Schema::hasTable('equipments')) {
            Schema::create('equipments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('part_no')->nullable();
                $table->string('model')->nullable();
                $table->string('model_name')->nullable();
                $table->string('model_number')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('serial_number')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->string('status')->default('available');
                $table->integer('quantity')->default(0);
                $table->integer('min_stock')->default(0);
                $table->integer('max_stock')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->string('supplier')->nullable();
                $table->date('purchase_date')->nullable();
                $table->date('warranty_date')->nullable();
                $table->string('withdrawal_type')->default('returnable');
                $table->text('notes')->nullable();
                $table->boolean('has_msds')->default(false);
                $table->string('msds_file_path')->nullable();
                $table->text('msds_details')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Create Transactions Table (Base Structure Only)
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('equipment_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type');
                $table->integer('quantity_change');
                $table->integer('returned_quantity')->default(0);
                $table->text('notes')->nullable();
                $table->string('status')->default('pending');
                $table->string('return_condition')->nullable();
                $table->timestamp('transaction_date')->useCurrent();
                $table->timestamp('admin_confirmed_at')->nullable();
                $table->timestamp('user_confirmed_at')->nullable();
                
                $table->foreign('equipment_id')->references('id')->on('equipments')->onDelete('cascade');
            });
        }

        // 4. Create Purchase Orders Table (Base Structure Only)
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number')->nullable();
                $table->unsignedBigInteger('ordered_by_user_id')->nullable();
                $table->timestamp('ordered_at')->nullable();
                $table->string('status')->default('ordered');
                $table->string('type')->default('general');
                $table->text('notes')->nullable();
                $table->string('requester_name')->nullable();
                $table->string('glpi_ticket_id')->nullable();
                $table->string('glpi_requester_name')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->decimal('total_amount', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        // 5. Create Purchase Order Items Table (Base Structure Only)
        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('item_description')->nullable();
                $table->integer('quantity_ordered');
                $table->integer('quantity_received')->default(0);
                $table->string('status')->default('pending');
                $table->string('inspection_status')->nullable();
                $table->text('inspection_notes')->nullable();
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->string('unit_name')->nullable();
                $table->unsignedBigInteger('requester_id')->nullable();
                
                $table->timestamps();
                
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            });
        }
        
        // 6. Create Stock Checks Table
        if (!Schema::hasTable('stock_checks')) {
            Schema::create('stock_checks', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->date('scheduled_date')->nullable();
                $table->unsignedBigInteger('checked_by_user_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
        
        // 6.1 Create Stock Check Items Table (Missing!)
        if (!Schema::hasTable('stock_check_items')) {
            Schema::create('stock_check_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_check_id');
                $table->unsignedBigInteger('equipment_id');
                $table->integer('expected_quantity')->default(0);
                $table->integer('actual_quantity')->nullable();
                $table->string('status')->default('pending'); // pending, matched, mismatch
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->foreign('stock_check_id')->references('id')->on('stock_checks')->onDelete('cascade');
                $table->foreign('equipment_id')->references('id')->on('equipments')->onDelete('cascade');
            });
        }

        // 7. Create Consumable Returns Table
        if (!Schema::hasTable('consumable_returns')) {
            Schema::create('consumable_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id');
                $table->integer('quantity_returned');
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        // 8. RBAC Tables (Permissions & Roles) - Critical fix for 403 Forbidden
        if (!Schema::hasTable('user_groups')) {
            Schema::create('user_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('hierarchy_level')->default(1);
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }
        
        if (!Schema::hasTable('service_user_roles')) {
            Schema::create('service_user_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // From depart_it_db (sync_ldap)
                $table->unsignedBigInteger('group_id');
                $table->timestamps();
                
                $table->foreign('group_id')->references('id')->on('user_groups')->onDelete('cascade');
            });
        }
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('group_permissions')) {
            Schema::create('group_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_group_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();
                
                $table->foreign('user_group_id')->references('id')->on('user_groups')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_returns');
        Schema::dropIfExists('stock_checks');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('equipments');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('units');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('categories');
    }
};
