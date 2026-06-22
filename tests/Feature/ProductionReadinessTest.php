<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_erp_routes_require_authentication(): void
    {
        $this->get('/erp')->assertRedirect('/login');
        $this->get('/sales')->assertRedirect('/login');
        $this->get('/inventory')->assertRedirect('/login');
        $this->getJson('/api/v1/erp/dashboard')->assertUnauthorized();
    }

    public function test_arabic_landing_is_rtl_and_uses_translated_content(): void
    {
        $this->get('/?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('نظام بصريات واضح وسهل');
    }

    public function test_authenticated_users_cannot_open_modules_without_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/sales')->assertForbidden();
    }

    public function test_offline_sync_creates_pending_draft_without_posting_accounting(): void
    {
        $user = User::factory()->create();
        $payload = [
            'uuid' => 'b3f65ca1-5fca-4c50-8f4a-28b0f862eb14',
            'module' => 'pos',
            'form_key' => 'pos-checkout-draft',
            'payload' => ['items[0][product_id]' => '1'],
            'idempotency_key' => 'b3f65ca1-5fca-4c50-8f4a-28b0f862eb14',
        ];

        $this->actingAs($user)->postJson('/offline-drafts', $payload)->assertAccepted()->assertJsonPath('status', 'pending_review');
        $this->assertDatabaseHas('offline_drafts', ['user_id' => $user->id, 'status' => 'pending_review']);
        $this->assertDatabaseCount('accounting_journals', 0);
    }

    public function test_registration_is_disabled_by_default(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_every_report_opens_as_html_and_csv_is_explicit(): void
    {
        $user = User::factory()->create();
        $permissionId = DB::table('permissions')->insertGetId([
            'module' => 'erp', 'action' => 'export', 'name' => 'Export Reports', 'slug' => 'erp.export',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Reporter', 'slug' => 'reporter', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        $user->roles()->attach($roleId);

        foreach (array_keys(config('erp.reports')) as $report) {
            $this->actingAs($user)->get(route('erp.reports.export', ['report' => $report]))
                ->assertOk()
                ->assertViewIs('erp.print-report');
            $this->actingAs($user)->get(route('erp.reports.export', ['report' => $report, 'format' => 'csv']))
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
    }
}
