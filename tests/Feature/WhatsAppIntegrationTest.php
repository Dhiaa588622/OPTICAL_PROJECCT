<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_can_verify_the_webhook_callback(): void
    {
        $this->insertSettings([
            'webhook_verify_token' => 'verify-this-token',
        ]);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-this-token&hub.challenge=challenge-123')
            ->assertOk()
            ->assertSeeText('challenge-123');

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
            ->assertForbidden();
    }

    public function test_live_webhooks_require_a_valid_meta_signature(): void
    {
        $secret = 'meta-app-secret';
        $this->insertSettings([
            'mode' => 'live',
            'app_secret' => encrypt($secret),
        ]);
        $payload = json_encode(['entry' => []], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)->assertOk()->assertJsonPath('status', 'processed');

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $payload)->assertForbidden();
    }

    public function test_admin_can_store_encrypted_credentials_and_test_the_number(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/whatsapp/settings', [
            'mode' => 'live',
            'api_version' => 'v23.0',
            'phone_number_id' => '123456789',
            'business_account_id' => '987654321',
            'webhook_verify_token' => 'verify-token',
            'access_token' => 'permanent-access-token',
            'app_secret' => 'meta-app-secret',
            'content_policy' => 'standard',
            'auto_send_enabled' => '1',
            'media_allowed' => '1',
            'retry_limit' => 3,
        ])->assertRedirect(route('whatsapp.app', ['page' => 'settings']));

        $settings = DB::table('whatsapp_settings')->first();
        $this->assertNotSame('permanent-access-token', $settings->access_token_ciphertext);
        $this->assertSame('permanent-access-token', decrypt($settings->access_token_ciphertext));
        $this->assertSame('meta-app-secret', decrypt($settings->app_secret));

        $this->actingAs($admin)->get('/whatsapp/settings')
            ->assertOk()
            ->assertSeeText('Meta Cloud API connection')
            ->assertSeeText('Callback URL');

        Http::fake([
            'https://graph.facebook.com/v23.0/123456789*' => Http::response([
                'display_phone_number' => '+966500000000',
                'verified_name' => 'Optical Store',
                'quality_rating' => 'GREEN',
            ]),
        ]);

        $this->actingAs($admin)->post('/whatsapp/settings/test')
            ->assertRedirect(route('whatsapp.app', ['page' => 'settings', 'lang' => 'en']))
            ->assertSessionHas('status', 'Connected to Optical Store (+966500000000).');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer permanent-access-token'));
    }

    private function adminUser(): User
    {
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Test Optical Company',
            'currency' => 'SAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $companyId,
            'name' => 'ERP Admin',
            'slug' => 'erp-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($roleId);

        return $user;
    }

    private function insertSettings(array $overrides = []): void
    {
        DB::table('whatsapp_settings')->insert(array_merge([
            'provider' => 'meta_cloud',
            'mode' => 'sandbox',
            'api_version' => 'v23.0',
            'webhook_verify_token' => 'verify-token',
            'content_policy' => 'standard',
            'auto_send_enabled' => true,
            'media_allowed' => true,
            'retry_limit' => 3,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
