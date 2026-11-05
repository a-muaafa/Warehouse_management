<?php

namespace Tests\Feature;

use App\Actions\Inventory\IssueStockAction;
use App\Data\IssueStockData;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\InventoryAudit;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComprehensiveWmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    private Warehouse $whA;

    private Warehouse $whB;

    private User $superAdmin;

    private User $whManagerA;

    private User $storekeeperA;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dept = Department::create(['name' => 'Electronics & IT']);
        $this->whA = Warehouse::create(['name' => 'Warehouse A', 'code' => 'WH-A', 'is_active' => true]);
        $this->whB = Warehouse::create(['name' => 'Warehouse B', 'code' => 'WH-B', 'is_active' => true]);

        $this->superAdmin = User::factory()->superAdmin()->create(['department_id' => $this->dept->id]);

        $this->whManagerA = User::factory()->warehouseManager()->create(['department_id' => $this->dept->id]);
        $this->whManagerA->warehouses()->attach($this->whA->id);

        $this->storekeeperA = User::factory()->storekeeper()->create(['department_id' => $this->dept->id]);
        $this->storekeeperA->warehouses()->attach($this->whA->id);

        $this->viewer = User::factory()->viewer()->create(['department_id' => $this->dept->id]);
        $this->viewer->warehouses()->attach($this->whA->id);
    }

    // -------------------------------------------------------------------------
    // 1. Authentication & Rate Limiting
    // -------------------------------------------------------------------------

    public function test_user_can_login_and_logout_and_failed_logins_are_logged(): void
    {
        $user = User::factory()->create([
            'username' => 'login_tester',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        // Failed login attempt
        $failResp = $this->post('/login', [
            'username' => 'login_tester',
            'password' => 'wrong_password',
        ]);
        $failResp->assertSessionHasErrors('username');
        $this->assertDatabaseHas('activity_logs', [
            'user_name' => 'login_tester',
            'action' => 'فشل تسجيل الدخول',
        ]);

        // Successful login
        $okResp = $this->post('/login', [
            'username' => 'login_tester',
            'password' => 'secret123',
        ]);
        $okResp->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Logout
        $logoutResp = $this->actingAs($user)->post('/logout');
        $logoutResp->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // 2. Multi-Batch Outbound Allocation & Central Ledger
    // -------------------------------------------------------------------------

    public function test_outbound_spanning_multiple_batches_records_pivot_items_and_immutable_ledger(): void
    {
        $item = Item::factory()->create([
            'name' => 'Laptop Pro',
            'department_id' => $this->dept->id,
            'quantity' => 0,
            'valuation_method' => 'fifo',
        ]);

        // Receive Batch 1: 5 units @ $100
        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 5,
            'unit_price' => 100,
            'batch_number' => 'LOT-LAP-1',
        ])->assertSessionHasNoErrors();

        // Receive Batch 2: 15 units @ $120
        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 15,
            'unit_price' => 120,
            'batch_number' => 'LOT-LAP-2',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(20.0, (float) $item->fresh()->quantity);

        // Issue 15 units (should take 5 from LOT-LAP-1 and 10 from LOT-LAP-2)
        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'out',
            'quantity' => 15,
            'unit_price' => 150,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(5.0, (float) $item->fresh()->quantity);

        $outTx = Transaction::where('item_id', $item->id)->where('type', 'out')->firstOrFail();
        $pivotRows = TransactionBatchItem::where('transaction_id', $outTx->id)->orderBy('id')->get();

        $this->assertCount(2, $pivotRows);
        $this->assertEquals(5.0, (float) $pivotRows[0]->quantity);
        $this->assertEquals(100.0, (float) $pivotRows[0]->unit_cost);
        $this->assertEquals(10.0, (float) $pivotRows[1]->quantity);
        $this->assertEquals(120.0, (float) $pivotRows[1]->unit_cost);

        // Verify InventoryMovement ledger entries (2 receipts + 2 batch issues = 4 ledger rows)
        $movements = InventoryMovement::where('item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(4, $movements);
        $this->assertEquals(5.0, (float) $movements->last()->quantity_after);

        // Verify immutability of InventoryMovement
        $this->expectException(\DomainException::class);
        $movements->first()->update(['quantity_change' => 999]);
    }

    public function test_completed_transaction_cannot_be_modified_or_deleted(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 0]);

        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_price' => 25,
        ]);

        $tx = Transaction::firstOrFail();

        $this->expectException(\DomainException::class);
        $tx->update(['quantity' => 999]);
    }

    public function test_duplicate_idempotency_key_is_rejected(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 0]);

        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 10,
            'idempotency_key' => 'IDEMP-KEY-001',
        ])->assertSessionHasNoErrors();

        // Second submission with same idempotency_key must fail validation
        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 10,
            'idempotency_key' => 'IDEMP-KEY-001',
        ])->assertSessionHasErrors('idempotency_key');

        $this->assertEquals(10.0, (float) $item->fresh()->quantity);
    }

    // -------------------------------------------------------------------------
    // 3. Warehouse Transfers: Approval, Partial Receipt, Discrepancy, Double-Receive Prevention
    // -------------------------------------------------------------------------

    public function test_transfer_approval_partial_receipt_and_discrepancy_resolution_workflow(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 50,
            'purchase_price' => 40.00,
        ]);

        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'batch_number' => 'SRC-LOT-50',
            'purchase_cost' => 40.00,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'status' => 'active',
        ]);

        // 1. Manager A creates transfer of 20 units from WH-A to WH-B
        $this->actingAs($this->whManagerA)->post(route('transfers.store'), [
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id' => $this->whB->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 20],
            ],
        ])->assertRedirect(route('transfers.index'));

        $transfer = WarehouseTransfer::firstOrFail();
        $this->assertEquals('pending', $transfer->status);

        // 2. Approve transfer
        $this->actingAs($this->whManagerA)->post(route('transfers.approve', $transfer))
            ->assertSessionHasNoErrors();
        $this->assertEquals('approved', $transfer->fresh()->status);

        // 3. Dispatch transfer (deducts 20 from WH-A)
        $this->actingAs($this->whManagerA)->post(route('transfers.dispatch', $transfer))
            ->assertSessionHasNoErrors();
        $this->assertEquals('in_transit', $transfer->fresh()->status);
        $this->assertEquals(30.0, (float) $item->fresh()->quantity);

        $transferItem = $transfer->items()->first();

        // 4. Receive partial quantity (18 received, 2 missing) at WH-B by SuperAdmin
        $this->actingAs($this->superAdmin)->post(route('transfers.receive', $transfer), [
            'received_quantities' => [$transferItem->id => 18],
            'discrepancy_reasons' => [$transferItem->id => 'Damaged box during transit'],
            'discrepancy_notes' => '2 units damaged on truck',
        ])->assertSessionHasNoErrors();

        $transfer->refresh();
        $transferItem->refresh();

        // Because 2 units are missing, status is partially_received (NOT completed)
        $this->assertEquals('partially_received', $transfer->status);
        $this->assertEquals(18.0, (float) $transferItem->received_quantity);
        $this->assertEquals(2.0, (float) $transferItem->missing_quantity);
        $this->assertEquals('Damaged box during transit', $transferItem->discrepancy_reason);
        $this->assertEquals(48.0, (float) $item->fresh()->quantity);

        // 5. Attempt to receive the same transfer a second time -> rejected
        $this->actingAs($this->superAdmin)->post(route('transfers.receive', $transfer))
            ->assertSessionHas('error');
        $this->assertEquals(48.0, (float) $item->fresh()->quantity);

        // 6. Resolve discrepancy by returning missing 2 units to source warehouse
        $this->actingAs($this->superAdmin)->post(route('transfers.resolve', $transfer), [
            'resolution' => 'return_to_source',
            'discrepancy_notes' => 'Found 2 units still at WH-A loading dock',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertEquals(50.0, (float) $item->fresh()->quantity);
    }

    // -------------------------------------------------------------------------
    // 4. Inventory Audits: Freeze Movements, Count, Reconcile, Immutability
    // -------------------------------------------------------------------------

    public function test_inventory_audit_freezes_movements_and_reconciles_discrepancies(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 20,
            'purchase_price' => 50.00,
        ]);

        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'batch_number' => 'AUD-LOT-20',
            'purchase_cost' => 50.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'status' => 'active',
        ]);

        // 1. Start audit with freeze_movements = true
        $this->actingAs($this->whManagerA)->post(route('audits.store'), [
            'title' => 'Q4 Warehouse A Audit',
            'type' => 'periodic_full',
            'warehouse_id' => $this->whA->id,
            'freeze_movements' => true,
        ])->assertRedirect();

        $audit = InventoryAudit::firstOrFail();
        $this->assertTrue($audit->freeze_movements);

        // 2. Attempt outbound transaction in WH-A while audit freezes movements -> blocked
        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'out',
            'quantity' => 2,
        ])->assertSessionHasErrors('warehouse_id');

        // 3. Submit physical count = 18 (shortage of -2)
        $auditItem = $audit->items()->firstOrFail();
        $this->actingAs($this->whManagerA)->post(route('audits.counts', $audit), [
            'counts' => [
                [
                    'id' => $auditItem->id,
                    'counted_quantity' => 18,
                    'discrepancy_reason' => 'Unrecorded breakage',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEquals('pending_approval', $audit->fresh()->status);
        $this->assertEquals(-2.0, (float) $auditItem->fresh()->discrepancy_quantity);

        // Stock is NOT adjusted until reconcile is approved
        $this->assertEquals(20.0, (float) $item->fresh()->quantity);

        // 4. Approve & Reconcile
        $this->actingAs($this->whManagerA)->post(route('audits.reconcile', $audit))
            ->assertRedirect(route('audits.show', $audit));

        $this->assertEquals('reconciled', $audit->fresh()->status);
        $this->assertEquals(18.0, (float) $item->fresh()->quantity);

        // Verify adjustment transaction and ledger entry were generated
        $this->assertDatabaseHas('transactions', [
            'item_id' => $item->id,
            'movement_reason' => 'audit_adjustment',
            'type' => 'out',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'item_id' => $item->id,
            'movement_type' => 'audit_adjustment',
            'quantity_before' => 20,
            'quantity_change' => -2,
            'quantity_after' => 18,
        ]);

        // 5. Attempting to modify counts on a reconciled audit is forbidden (403)
        $this->actingAs($this->whManagerA)->post(route('audits.counts', $audit), [
            'counts' => [
                ['id' => $auditItem->id, 'counted_quantity' => 25],
            ],
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 5. Master Data Authorization & Audit Log Protection
    // -------------------------------------------------------------------------

    public function test_viewer_cannot_create_or_delete_suppliers_or_warehouses(): void
    {
        $supplier = Supplier::create(['company_name' => 'Global Parts Co', 'is_active' => true]);

        $this->actingAs($this->viewer)->post(route('suppliers.store'), [
            'company_name' => 'Unauthorized Supplier',
        ])->assertStatus(403);

        $this->actingAs($this->viewer)->delete(route('suppliers.destroy', $supplier))
            ->assertStatus(403);

        $this->actingAs($this->viewer)->post(route('warehouses.store'), [
            'name' => 'Unauthorized WH',
            'code' => 'WH-UNAUTH',
        ])->assertStatus(403);
    }

    public function test_audit_log_records_old_and_new_values_and_masks_passwords(): void
    {
        $item = Item::factory()->create([
            'name' => 'Old Item Name',
            'barcode' => 'BC-AUDIT-1',
            'purchase_price' => 10.00,
            'department_id' => $this->dept->id,
        ]);

        $this->actingAs($this->whManagerA)->put(route('supervisor.items.update', $item), [
            'name' => 'New Item Name',
            'barcode' => 'BC-AUDIT-1',
            'purchase_price' => 15.50,
            'selling_price' => 20.00,
            'valuation_method' => 'fifo',
            'min_alert_level' => 5,
        ])->assertRedirect(route('supervisor.items.index'));

        $log = ActivityLog::where('action', 'تعديل صنف')->latest('id')->firstOrFail();
        $this->assertEquals('Old Item Name', $log->old_values['name']);
        $this->assertEquals('New Item Name', $log->new_values['name']);

        // Non-super-admin cannot delete ActivityLog
        $this->actingAs($this->whManagerA);
        $this->expectException(\DomainException::class);
        $log->delete();
    }

    // -------------------------------------------------------------------------
    // 6. Production Seeder Guard & wms:create-admin CLI Command
    // -------------------------------------------------------------------------

    public function test_demo_seeder_is_blocked_in_production_and_create_admin_command_works(): void
    {
        // Clear users created in setUp
        User::query()->forceDelete();
        $this->assertEquals(0, User::count());

        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);
            $this->assertEquals(0, User::count(), 'DatabaseSeeder must not seed demo users in production');

            $this->artisan('db:seed', ['--class' => DemoUsersSeeder::class, '--force' => true]);
            $this->assertEquals(0, User::count(), 'DemoUsersSeeder must abort when called directly in production');
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }

        // Reject short password (< 12 chars)
        $this->artisan('wms:create-admin', [
            'email' => 'short@company.com',
            'username' => 'short_admin',
        ])
            ->expectsQuestion('Password (min 12 chars)', 'short123')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'short@company.com']);

        // Accept strong password (>= 12 chars)
        $this->artisan('wms:create-admin', [
            'email' => 'chief@company.com',
            'username' => 'chief_admin',
        ])
            ->expectsQuestion('Password (min 12 chars)', 'StrongPass#2026!')
            ->assertExitCode(0);

        $admin = User::where('email', 'chief@company.com')->firstOrFail();
        $this->assertEquals('super_admin', $admin->role);
        $this->assertTrue($admin->isSuperAdmin());
    }

    // -------------------------------------------------------------------------
    // 7. Database-Level Append-Only Triggers on Ledger & Activity Logs
    // -------------------------------------------------------------------------

    public function test_database_triggers_block_direct_sql_update_and_delete_on_inventory_movements_and_activity_logs(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 0,
        ]);

        $this->actingAs($this->storekeeperA)->post(route('transactions.store'), [
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_price' => 50,
        ])->assertSessionHasNoErrors();

        $movement = InventoryMovement::firstOrFail();

        // Bypass Eloquent events using raw Query Builder -> DB trigger must reject with QueryException
        $updateBlocked = false;
        try {
            DB::table('inventory_movements')
                ->where('id', $movement->id)
                ->update(['quantity_after' => 0]);
        } catch (QueryException) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'DB trigger must prevent direct UPDATE on inventory_movements');

        $deleteBlocked = false;
        try {
            DB::table('inventory_movements')
                ->where('id', $movement->id)
                ->delete();
        } catch (QueryException) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'DB trigger must prevent direct DELETE on inventory_movements');

        $log = ActivityLog::firstOrFail();
        $logDeleteBlocked = false;
        try {
            DB::table('activity_logs')
                ->where('id', $log->id)
                ->delete();
        } catch (QueryException) {
            $logDeleteBlocked = true;
        }
        $this->assertTrue($logDeleteBlocked, 'DB trigger must prevent direct DELETE on activity_logs');
    }

    // -------------------------------------------------------------------------
    // 8. Concurrent / Sequential Stock Issue Protection via IssueStockAction
    // -------------------------------------------------------------------------

    public function test_concurrent_outbound_attempts_cannot_overdraw_stock(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 10,
            'valuation_method' => 'fifo',
        ]);

        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->whA->id,
            'batch_number' => 'CONC-BATCH-10',
            'purchase_cost' => 30.00,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'status' => 'active',
        ]);

        $action = app(IssueStockAction::class);

        $attempt = function () use ($action, $item): string {
            try {
                $action->execute(
                    new IssueStockData(
                        itemId: $item->id,
                        warehouseId: $this->whA->id,
                        quantity: '8',
                        reason: 'sale_delivery',
                    ),
                    $this->storekeeperA
                );

                return 'ok';
            } catch (\DomainException) {
                return 'insufficient';
            }
        };

        $results = [$attempt(), $attempt()];

        $this->assertEqualsCanonicalizing(['ok', 'insufficient'], $results);
        $this->assertEquals(2.0, (float) $item->fresh()->quantity);
    }
}
