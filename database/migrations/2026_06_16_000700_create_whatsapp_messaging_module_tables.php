<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table): void {
                if (! Schema::hasColumn('patients', 'whatsapp_opt_in')) {
                    $table->boolean('whatsapp_opt_in')->default(false)->index();
                }
                if (! Schema::hasColumn('patients', 'whatsapp_opt_out')) {
                    $table->boolean('whatsapp_opt_out')->default(false)->index();
                }
                if (! Schema::hasColumn('patients', 'whatsapp_consent_at')) {
                    $table->dateTime('whatsapp_consent_at')->nullable();
                }
                if (! Schema::hasColumn('patients', 'whatsapp_consent_source')) {
                    $table->string('whatsapp_consent_source')->nullable();
                }
                if (! Schema::hasColumn('patients', 'whatsapp_content_policy')) {
                    $table->string('whatsapp_content_policy')->default('standard');
                }
            });
        }

        Schema::create('whatsapp_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('meta_cloud');
            $table->string('mode')->default('sandbox')->index();
            $table->string('api_version')->default('v23.0');
            $table->string('phone_number_id')->nullable();
            $table->string('business_account_id')->nullable();
            $table->string('webhook_verify_token')->nullable();
            $table->string('app_secret')->nullable();
            $table->string('access_token_masked')->nullable();
            $table->text('access_token_ciphertext')->nullable();
            $table->string('default_template_language')->default('en_US');
            $table->string('content_policy')->default('standard');
            $table->boolean('auto_send_enabled')->default(true);
            $table->boolean('media_allowed')->default(true);
            $table->unsignedTinyInteger('retry_limit')->default(3);
            $table->string('status')->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversation_number')->unique();
            $table->string('whatsapp_number')->index();
            $table->string('contact_name')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('last_message_preview')->nullable();
            $table->dateTime('last_message_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'last_message_at']);
            $table->index(['patient_id', 'status']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->default('utility')->index();
            $table->string('trigger_key')->nullable()->index();
            $table->string('provider_template_name')->nullable();
            $table->string('language_code')->default('en_US');
            $table->string('header_type')->default('none');
            $table->string('header_text')->nullable();
            $table->text('body');
            $table->string('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('send_sensitive_details')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('whatsapp_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('trigger_key')->index();
            $table->string('status')->default('active')->index();
            $table->string('timing')->default('immediate');
            $table->integer('offset_minutes')->default(0);
            $table->boolean('requires_opt_in')->default(true);
            $table->json('conditions')->nullable();
            $table->json('action_config')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();
            $table->index(['trigger_key', 'status']);
        });

        if (Schema::hasTable('patient_whatsapp_messages')) {
            Schema::table('patient_whatsapp_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('patient_whatsapp_messages', 'whatsapp_conversation_id')) {
                    $table->unsignedBigInteger('whatsapp_conversation_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'assigned_to')) {
                    $table->unsignedBigInteger('assigned_to')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'whatsapp_template_id')) {
                    $table->unsignedBigInteger('whatsapp_template_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'whatsapp_automation_rule_id')) {
                    $table->unsignedBigInteger('whatsapp_automation_rule_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'message_type')) {
                    $table->string('message_type')->default('text')->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'context_type')) {
                    $table->string('context_type')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'context_id')) {
                    $table->unsignedBigInteger('context_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'provider_event_id')) {
                    $table->string('provider_event_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'idempotency_key')) {
                    $table->string('idempotency_key')->nullable()->unique();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'queued_at')) {
                    $table->dateTime('queued_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'delivered_at')) {
                    $table->dateTime('delivered_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'read_at')) {
                    $table->dateTime('read_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'failed_at')) {
                    $table->dateTime('failed_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'reply_at')) {
                    $table->dateTime('reply_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'retry_count')) {
                    $table->unsignedTinyInteger('retry_count')->default(0);
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'error_code')) {
                    $table->string('error_code')->nullable();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'provider_payload')) {
                    $table->json('provider_payload')->nullable();
                }
                if (! Schema::hasColumn('patient_whatsapp_messages', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
            });
        }

        Schema::create('whatsapp_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_whatsapp_message_id')->nullable()->index();
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction')->default('out')->index();
            $table->string('media_id')->nullable()->index();
            $table->string('media_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256')->nullable();
            $table->string('status')->default('stored')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_uid')->unique();
            $table->string('provider')->default('meta_cloud');
            $table->string('event_type')->index();
            $table->string('phone_number_id')->nullable()->index();
            $table->string('whatsapp_number')->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->dateTime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_consent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->string('whatsapp_number')->index();
            $table->string('consent_status')->index();
            $table->string('source')->nullable();
            $table->string('policy_version')->default('standard');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_internal_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->string('visibility')->default('internal')->index();
            $table->boolean('is_escalation')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_internal_notes');
        Schema::dropIfExists('whatsapp_consent_events');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_message_attachments');
        Schema::dropIfExists('whatsapp_automation_rules');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_settings');
    }
};
