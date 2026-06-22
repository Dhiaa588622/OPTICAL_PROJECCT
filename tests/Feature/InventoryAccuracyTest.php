<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_issue_reduces_stock_and_return_increases_it(): void
    {
        [$user, $productId, $branchId] = $this->inventoryFixture(10);

        $this->actingAs($user)->postJson('/api/v1/inventory/operations/issue-sale', [
            'product_id' => $productId, 'branch_id' => $branchId, 'quantity' => 3,
        ])->assertOk()->assertJsonPath('result.balance_after', 7);
        $this->actingAs($user)->postJson('/api/v1/inventory/operations/receive-return', [
            'product_id' => $productId, 'branch_id' => $branchId, 'quantity' => 2, 'unit_cost' => 20,
        ])->assertOk();

        $this->assertDatabaseHas('inventory_stock_levels', ['product_id' => $productId, 'branch_id' => $branchId, 'qty_on_hand' => 9]);
        $this->assertDatabaseHas('inventory_stock_movements', ['product_id' => $productId, 'movement_type' => 'sale_issue', 'direction' => 'out']);
        $this->assertDatabaseHas('inventory_stock_movements', ['product_id' => $productId, 'movement_type' => 'sale_return', 'direction' => 'in']);
    }

    public function test_issue_and_adjustment_block_negative_stock_without_permission(): void
    {
        [$user, $productId, $branchId] = $this->inventoryFixture(5);

        $this->actingAs($user)->postJson('/api/v1/inventory/operations/issue-sale', [
            'product_id' => $productId, 'branch_id' => $branchId, 'quantity' => 6,
        ])->assertUnprocessable();
        $this->actingAs($user)->post('/inventory/stock-adjustments', [
            'product_id' => $productId, 'branch_id' => $branchId, 'quantity_change' => -6, 'reason' => 'count_variance',
        ])->assertSessionHasErrors('quantity_change');

        $this->assertDatabaseHas('inventory_stock_levels', ['product_id' => $productId, 'qty_on_hand' => 5]);
    }

    public function test_transfer_moves_the_exact_quantity_between_branches(): void
    {
        [$user, $productId, $fromBranch] = $this->inventoryFixture(8);
        $companyId = (int) DB::table('branches')->where('id', $fromBranch)->value('company_id');
        $toBranch = $this->branch($companyId, 'TO');

        $transfer = $this->actingAs($user)->postJson('/api/v1/inventory/transfers', [
            'from_branch_id' => $fromBranch,
            'to_branch_id' => $toBranch,
            'lines' => [['product_id' => $productId, 'quantity' => 3]],
        ])->assertCreated()->json('id');
        $this->actingAs($user)->postJson('/api/v1/inventory/operations/ship-transfer', ['transfer_id' => $transfer])->assertOk();
        $this->actingAs($user)->postJson('/api/v1/inventory/operations/receive-transfer', ['transfer_id' => $transfer])->assertOk();

        $this->assertDatabaseHas('inventory_stock_levels', ['product_id' => $productId, 'branch_id' => $fromBranch, 'qty_on_hand' => 5]);
        $this->assertDatabaseHas('inventory_stock_levels', ['product_id' => $productId, 'branch_id' => $toBranch, 'qty_on_hand' => 3]);
        $this->assertDatabaseHas('inventory_transfers', ['id' => $transfer, 'status' => 'received']);
    }

    private function inventoryFixture(float $quantity): array
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Inventory Company', 'currency' => 'SAR', 'created_at' => now(), 'updated_at' => now()]);
        $branchId = $this->branch($companyId, 'FROM');
        $categoryId = DB::table('product_categories')->insertGetId(['company_id' => $companyId, 'name' => 'Frames', 'type' => 'frame', 'created_at' => now(), 'updated_at' => now()]);
        $productId = DB::table('products')->insertGetId([
            'company_id' => $companyId, 'product_category_id' => $categoryId, 'sku' => 'SKU-'.str()->random(8), 'type' => 'frame', 'name' => 'Test Frame',
            'cost_price' => 20, 'retail_price' => 50, 'tax_type' => 'standard', 'tax_rate' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $locationId = (int) DB::table('inventory_locations')->where('branch_id', $branchId)->value('id');
        DB::table('inventory_stock_levels')->insert([
            'product_id' => $productId, 'branch_id' => $branchId, 'inventory_location_id' => $locationId, 'qty_on_hand' => $quantity,
            'qty_reserved' => 0, 'average_cost' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId(['module' => 'inventory', 'action' => 'manage', 'name' => 'Manage inventory', 'slug' => 'inventory.manage', 'created_at' => now(), 'updated_at' => now()]);
        $adjustPermissionId = DB::table('permissions')->insertGetId(['module' => 'inventory', 'action' => 'adjust', 'name' => 'Adjust inventory', 'slug' => 'inventory.adjust', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId(['company_id' => $companyId, 'name' => 'Inventory Tester', 'slug' => 'inventory-tester', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$permissionId, $adjustPermissionId] as $id) {
            DB::table('permission_role')->insert(['permission_id' => $id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($roleId);
        $user->branches()->attach($branchId, ['is_default' => true]);

        return [$user, $productId, $branchId];
    }

    private function branch(int $companyId, string $code): int
    {
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => $code, 'name' => $code.' Branch', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_locations')->insert(['branch_id' => $branchId, 'code' => $code.'-SELL', 'name' => 'Sellable', 'type' => 'shop_floor', 'is_sellable' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $branchId;
    }
}
