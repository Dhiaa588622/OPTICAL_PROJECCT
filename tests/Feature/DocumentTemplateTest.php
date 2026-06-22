<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_settings_are_saved_and_audited(): void
    {
        [$user, $companyId] = $this->context();

        $this->actingAs($user)->get('/configuration/template-settings/edit')
            ->assertOk()->assertSee('Template Settings');
        $this->actingAs($user)->put('/configuration/template-settings', [
            'company_name' => 'Configured Focus', 'address' => 'Configured Address', 'phone' => '111222333', 'tax_number' => 'TAX-01',
            'header_text_en' => 'English Header', 'header_text_ar' => 'Arabic Header', 'footer_text_en' => 'English Footer', 'footer_text_ar' => 'Arabic Footer',
            'prepared_by_en' => 'Prepared by', 'prepared_by_ar' => 'Prepared Arabic', 'approved_by_en' => 'Approved by', 'approved_by_ar' => 'Approved Arabic',
            'received_by_en' => 'Received by', 'received_by_ar' => 'Received Arabic', 'paper_size' => 'a4', 'template_language' => 'bilingual',
            'default_template' => 'focus', 'primary_color' => '#c9a55d', 'secondary_color' => '#73756f', 'show_watermark' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['company_id' => $companyId, 'group' => 'document_templates', 'key' => 'company_name', 'value' => 'Configured Focus']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'configuration.document_templates.updated']);
    }

    public function test_sales_invoice_renders_branded_html_and_real_pdf(): void
    {
        [$user, $companyId, $branchId] = $this->context();
        $customerId = DB::table('sales_customers')->insertGetId([
            'company_id' => $companyId, 'name' => 'Patient Customer', 'phone' => '5555',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $customerId, 'invoice_number' => 'INV-DOC-1',
            'status' => 'posted', 'invoice_date' => now()->toDateString(), 'subtotal' => 100, 'tax_total' => 15, 'grand_total' => 115,
            'paid_total' => 50, 'balance_due' => 65, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId, 'description' => 'Optical frame', 'quantity' => 1, 'unit_price' => 100,
            'tax_rate' => 15, 'tax_amount' => 15, 'line_total' => 115, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/documents/sales-invoices/'.$invoiceId)
            ->assertOk()->assertSee('INV-DOC-1')->assertSee('Patient Customer')->assertSee('Optical frame');
        $response = $this->actingAs($user)->get('/documents/sales-invoices/'.$invoiceId.'?format=pdf');
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function context(): array
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Focus Test Optics', 'currency' => 'SAR', 'created_at' => now(), 'updated_at' => now()]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'MAIN', 'name' => 'Main Branch', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $user = User::factory()->create();
        $roleId = DB::table('roles')->insertGetId(['company_id' => $companyId, 'name' => 'ERP Admin', 'slug' => 'erp-admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $companyId, $branchId];
    }
}
