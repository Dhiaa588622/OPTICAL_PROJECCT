<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_quotation_lines_can_be_replaced_and_audited(): void
    {
        [$user, $companyId, $branchId, $productId] = $this->salesFixture();
        $quotationId = DB::table('sales_quotations')->insertGetId([
            'company_id' => $companyId, 'branch_id' => $branchId, 'quotation_number' => 'QUO-EDIT', 'status' => 'draft',
            'quoted_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->patchJson('/api/v1/sales-pos/quotations/'.$quotationId, [
            'branch_id' => $branchId,
            'notes' => 'Edited safely',
            'items' => [['product_id' => $productId, 'quantity' => 2, 'unit_price' => 80]],
        ])->assertOk()->assertJsonPath('status', 'updated');

        $this->assertDatabaseHas('sales_quotations', ['id' => $quotationId, 'notes' => 'Edited safely', 'subtotal' => 160]);
        $this->assertDatabaseHas('sales_quotation_items', ['sales_quotation_id' => $quotationId, 'product_id' => $productId, 'quantity' => 2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales.quotation.updated', 'auditable_id' => $quotationId]);
    }

    public function test_non_draft_quotation_cannot_be_edited(): void
    {
        [$user, $companyId, $branchId, $productId] = $this->salesFixture();
        $quotationId = DB::table('sales_quotations')->insertGetId([
            'company_id' => $companyId, 'branch_id' => $branchId, 'quotation_number' => 'QUO-SENT', 'status' => 'sent',
            'quoted_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->patchJson('/api/v1/sales-pos/quotations/'.$quotationId, [
            'branch_id' => $branchId,
            'items' => [['product_id' => $productId, 'quantity' => 1, 'unit_price' => 80]],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('sales_quotation_items', ['sales_quotation_id' => $quotationId]);
    }

    private function salesFixture(): array
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Sales Company', 'currency' => 'SAR', 'created_at' => now(), 'updated_at' => now()]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'SALES', 'name' => 'Sales Branch', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['company_id' => $companyId, 'name' => 'Frames', 'type' => 'frame', 'created_at' => now(), 'updated_at' => now()]);
        $productId = DB::table('products')->insertGetId([
            'company_id' => $companyId, 'product_category_id' => $categoryId, 'sku' => 'EDIT-SKU', 'type' => 'frame', 'name' => 'Editable Frame',
            'cost_price' => 25, 'retail_price' => 80, 'tax_type' => 'standard', 'tax_rate' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId(['module' => 'sales_pos', 'action' => 'checkout', 'name' => 'Sales checkout', 'slug' => 'sales_pos.checkout', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId(['company_id' => $companyId, 'name' => 'Sales Editor', 'slug' => 'sales-editor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        $user->roles()->attach($roleId);

        return [$user, $companyId, $branchId, $productId];
    }
}
