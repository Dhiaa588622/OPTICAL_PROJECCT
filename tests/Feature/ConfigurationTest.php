<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SetupOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_crud_updates_runtime_dropdowns_immediately(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/erp/configuration?group=payment_methods')
            ->assertOk()
            ->assertSeeText('Create configuration item');

        $response = $this->actingAs($admin)->postJson('/api/v1/configuration/payment_methods', [
            'key' => 'mada_test',
            'label_en' => 'Mada test terminal',
            'label_ar' => 'مدى تجريبي',
            'value' => 'mada_test',
            'value_type' => 'string',
            'sort_order' => 50,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('status', 'created');

        $id = $response->json('id');
        $this->assertSame('Mada test terminal', app(SetupOptions::class)->options('payment_methods')->get('mada_test'));

        $this->actingAs($admin)->patchJson('/api/v1/configuration/payment_methods/'.$id, [
            'key' => 'mada_test',
            'label_en' => 'Updated Mada terminal',
            'value' => 'mada_test',
            'value_type' => 'string',
            'sort_order' => 50,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('status', 'updated');

        $this->assertSame('Updated Mada terminal', app(SetupOptions::class)->options('payment_methods')->get('mada_test'));

        $this->actingAs($admin)->deleteJson('/api/v1/configuration/payment_methods/'.$id)
            ->assertOk()->assertJsonPath('status', 'deleted');
        $this->assertFalse(app(SetupOptions::class)->options('payment_methods')->has('mada_test'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'configuration.updated', 'auditable_id' => $id]);
    }

    public function test_system_options_are_deactivated_instead_of_destroyed(): void
    {
        $admin = $this->admin();
        $option = DB::table('configuration_options')->where('group', 'payment_methods')->where('is_system', true)->first();

        $this->actingAs($admin)->deleteJson('/api/v1/configuration/payment_methods/'.$option->id)
            ->assertOk()->assertJsonPath('status', 'deactivated');

        $this->assertDatabaseHas('configuration_options', ['id' => $option->id, 'is_active' => false]);
    }

    public function test_branch_configuration_can_be_created_searched_edited_and_deactivated(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/configuration/branches', [
            'code' => 'TEST-BR',
            'name' => 'Test Branch',
            'is_active' => true,
        ])->assertCreated();
        $id = $response->json('id');

        $this->actingAs($admin)->getJson('/api/v1/configuration/branches?q=TEST-BR')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->patchJson('/api/v1/configuration/branches/'.$id, [
            'code' => 'TEST-BR',
            'name' => 'Edited Branch',
            'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('branches', ['id' => $id, 'name' => 'Edited Branch']);

        $this->actingAs($admin)->deleteJson('/api/v1/configuration/branches/'.$id)
            ->assertOk()->assertJsonPath('status', 'deactivated');
        $this->assertDatabaseHas('branches', ['id' => $id, 'is_active' => false]);
    }

    public function test_user_without_settings_permission_cannot_change_configuration(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/configuration/brands', [
            'key' => 'blocked', 'label_en' => 'Blocked',
        ])->assertForbidden();
    }

    public function test_role_permissions_can_be_managed_from_configuration(): void
    {
        $admin = $this->admin();
        $permissionId = (int) DB::table('permissions')->where('slug', 'inventory.negative_stock')->value('id');

        $response = $this->actingAs($admin)->postJson('/api/v1/configuration/roles', [
            'name' => 'Stock Override',
            'slug' => 'stock-override',
            'permission_ids' => [$permissionId],
        ])->assertCreated();

        $this->assertDatabaseHas('permission_role', ['role_id' => $response->json('id'), 'permission_id' => $permissionId]);
        $this->actingAs($admin)->get('/erp/configuration?group=roles')->assertOk()->assertSeeText('Stock Override');
    }

    private function admin(): User
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Configuration Company', 'currency' => 'SAR', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId(['company_id' => $companyId, 'name' => 'ERP Admin', 'slug' => 'erp-admin', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        $user->roles()->attach($roleId);

        return $user;
    }
}
