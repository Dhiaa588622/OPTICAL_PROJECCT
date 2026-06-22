<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasTable('patient_whatsapp_messages')) {
            return;
        }

        $now = now();
        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchIds = DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $patients = DB::table('patients')->pluck('id', 'patient_code')->map(fn ($id) => (int) $id)->all();

        if (! $companyId || $branchIds === [] || $patients === []) {
            return;
        }

        $staffId = $this->user('whatsapp@example.com', 'WhatsApp Agent', $now);
        $managerId = $this->user('manager@example.com', 'Store Manager', $now);

        $this->permissionsAndRoles($companyId, $now);
        $this->settings($companyId, $now);
        $templates = $this->templates($companyId, $now);
        $this->automations($companyId, $branchIds, $templates, $now);
        $this->consent($patients, $staffId, $now);
        $this->conversations($companyId, $branchIds, $patients, $staffId, $managerId, $templates, $now);
        $this->webhookSamples($now);
    }

    private function user(string $email, string $name, $now): int
    {
        $id = DB::table('users')->where('email', $email)->value('id');
        if ($id) {
            return (int) $id;
        }

        return DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function settings(int $companyId, $now): void
    {
        DB::table('whatsapp_settings')->updateOrInsert(
            ['company_id' => $companyId, 'provider' => 'meta_cloud'],
            [
                'mode' => 'sandbox',
                'api_version' => config('whatsapp.provider.api_version'),
                'phone_number_id' => 'sandbox-phone-number-id',
                'business_account_id' => 'sandbox-business-account-id',
                'webhook_verify_token' => 'optical-demo-verify-token',
                'access_token_masked' => 'demo******token',
                'default_template_language' => 'en_US',
                'content_policy' => 'standard',
                'auto_send_enabled' => true,
                'media_allowed' => true,
                'retry_limit' => 3,
                'status' => 'active',
                'settings' => json_encode(['sandbox_delivery' => 'messages are stored locally']),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function templates(int $companyId, $now): array
    {
        $rows = [
            'appointment_confirmation' => ['Appointment confirmation', 'utility', 'appointment_confirmation_v1', 'Hello {{patient_name}}, your appointment is scheduled for {{appointment_date}}. Reply YES to confirm.'],
            'appointment_reminder' => ['Appointment reminder', 'utility', 'appointment_reminder_v1', 'Hello {{patient_name}}, reminder for your optical appointment on {{appointment_date}}. We look forward to seeing you.'],
            'appointment_reschedule' => ['Appointment reschedule', 'utility', 'appointment_reschedule_v1', 'Hello {{patient_name}}, your appointment has been rescheduled to {{appointment_date}}. Reply YES to confirm.'],
            'appointment_cancellation' => ['Appointment cancellation', 'utility', 'appointment_cancelled_v1', 'Hello {{patient_name}}, your appointment was cancelled. Contact us to book a new time.'],
            'optical_order_confirmation' => ['Optical order confirmation', 'utility', 'order_confirmed_v1', 'Hello {{patient_name}}, your optical order {{order_number}} is confirmed and now in process.'],
            'order_sent_to_lab' => ['Order sent to lab', 'utility', 'order_lab_v1', 'Hello {{patient_name}}, your optical order {{order_number}} has been sent to the lab.'],
            'order_ready_for_pickup' => ['Order ready for pickup', 'utility', 'order_ready_v1', 'Good news {{patient_name}}, your optical order {{order_number}} is ready for pickup.'],
            'payment_reminder' => ['Payment reminder', 'utility', 'payment_reminder_v1', 'Hello {{patient_name}}, your balance is {{balance_due}}. Please complete payment at pickup.'],
            'invoice_message' => ['Invoice message', 'utility', 'invoice_message_v1', 'Hello {{patient_name}}, your invoice {{invoice_number}} is ready. Thank you for choosing ClearView Optical.'],
            'follow_up_message' => ['Follow-up message', 'service', 'follow_up_v1', 'Hello {{patient_name}}, this is a friendly follow-up from ClearView Optical. Do you need help with your eyewear?'],
            'customer_service_reply' => ['Customer service reply', 'service', null, 'Hello {{patient_name}}, thanks for contacting ClearView Optical. How can we help you today?'],
        ];

        foreach ($rows as $key => [$name, $category, $providerName, $body]) {
            DB::table('whatsapp_templates')->updateOrInsert(
                ['slug' => Str::slug($name)],
                [
                    'company_id' => $companyId,
                    'name' => $name,
                    'category' => $category,
                    'trigger_key' => $key,
                    'provider_template_name' => $providerName,
                    'language_code' => 'en_US',
                    'header_type' => 'none',
                    'body' => $body,
                    'footer' => 'ClearView Optical',
                    'variables' => json_encode($this->variables($body)),
                    'send_sensitive_details' => false,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('whatsapp_templates')->pluck('id', 'trigger_key')->map(fn ($id) => (int) $id)->all();
    }

    private function automations(int $companyId, array $branchIds, array $templates, $now): void
    {
        $rows = [
            ['Appointment created confirmation', 'appointment_created', 'appointment_confirmation', 'immediate', 0],
            ['Tomorrow appointment reminder', 'appointment_tomorrow', 'appointment_reminder', 'before_event', -1440],
            ['Appointment rescheduled message', 'appointment_rescheduled', 'appointment_reschedule', 'immediate', 0],
            ['Appointment cancelled message', 'appointment_cancelled', 'appointment_cancellation', 'immediate', 0],
            ['Optical order confirmed message', 'optical_order_confirmed', 'optical_order_confirmation', 'immediate', 0],
            ['Order ready pickup message', 'order_ready_for_pickup', 'order_ready_for_pickup', 'immediate', 0],
            ['Invoice created message', 'invoice_created', 'invoice_message', 'immediate', 0],
            ['Payment overdue reminder', 'payment_overdue', 'payment_reminder', 'scheduled', 0],
            ['Follow-up due message', 'follow_up_due', 'follow_up_message', 'scheduled', 0],
        ];

        foreach ($rows as [$name, $trigger, $templateKey, $timing, $offset]) {
            DB::table('whatsapp_automation_rules')->updateOrInsert(
                ['name' => $name, 'trigger_key' => $trigger],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchIds['MAIN'] ?? reset($branchIds),
                    'whatsapp_template_id' => $templates[$templateKey] ?? null,
                    'status' => 'active',
                    'timing' => $timing,
                    'offset_minutes' => $offset,
                    'requires_opt_in' => true,
                    'conditions' => json_encode(['skip_if_opted_out' => true]),
                    'action_config' => json_encode(['channel' => 'whatsapp']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function consent(array $patients, int $staffId, $now): void
    {
        $statuses = [
            'PT-1001' => ['opt_in', 'registration_form', 'standard'],
            'PT-1002' => ['opt_in', 'pos_checkout', 'standard'],
            'PT-1003' => ['opt_in', 'appointment_booking', 'standard'],
            'PT-1004' => ['opt_out', 'manual', 'minimal'],
        ];

        foreach ($statuses as $code => [$status, $source, $policy]) {
            if (empty($patients[$code])) {
                continue;
            }
            $patient = DB::table('patients')->where('id', $patients[$code])->first();
            $number = $patient->whatsapp_number ?: $patient->phone ?: '+966500000000';

            DB::table('patients')->where('id', $patient->id)->update([
                'whatsapp_number' => $number,
                'whatsapp_opt_in' => $status === 'opt_in',
                'whatsapp_opt_out' => $status === 'opt_out',
                'whatsapp_consent_at' => $now->copy()->subDays(8),
                'whatsapp_consent_source' => $source,
                'whatsapp_content_policy' => $policy,
                'updated_at' => $now,
            ]);

            DB::table('whatsapp_consent_events')->updateOrInsert(
                ['patient_id' => $patient->id, 'consent_status' => $status, 'source' => $source],
                [
                    'whatsapp_number' => $number,
                    'policy_version' => $policy,
                    'recorded_by' => $staffId,
                    'recorded_at' => $now->copy()->subDays(8),
                    'notes' => $status === 'opt_in' ? 'Patient agreed to WhatsApp service notifications.' : 'Patient requested no WhatsApp messages.',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function conversations(int $companyId, array $branchIds, array $patients, int $staffId, int $managerId, array $templates, $now): void
    {
        $rows = [
            [
                'WAC-1001', 'PT-1001', 'MAIN', $staffId, null, 'open', 'normal',
                [
                    ['out', 'appointment_confirmation', 'Hello Sara Ahmed, your appointment is scheduled for today at 09:30. Reply YES to confirm.', 'sent', $now->copy()->subHours(22)],
                    ['in', null, 'YES confirmed. Can I also check my lens coating options?', 'replied', $now->copy()->subHours(20)],
                    ['out', 'customer_service_reply', 'Sure, we can show standard AR, premium AR, and blue-block options during your visit.', 'read', $now->copy()->subHours(19)],
                ],
            ],
            [
                'WAC-1002', 'PT-1002', 'MAIN', $staffId, $managerId, 'pending', 'urgent',
                [
                    ['out', 'payment_reminder', 'Hello Omar Khaled, your pickup balance is 350.00. Please complete payment at pickup.', 'delivered', $now->copy()->subHours(5)],
                    ['in', null, 'Can I pay by card link before pickup?', 'replied', $now->copy()->subHours(4)],
                    ['out', 'customer_service_reply', 'Yes, we can send a payment link. A manager will confirm the remaining balance.', 'sent', $now->copy()->subHours(3)],
                ],
            ],
            [
                'WAC-1003', 'PT-1003', 'NORTH', null, null, 'open', 'normal',
                [
                    ['out', 'order_ready_for_pickup', 'Good news Maha Saleh, your optical order is ready for pickup.', 'failed', $now->copy()->subHours(2)],
                ],
            ],
        ];

        foreach ($rows as [$number, $patientCode, $branchCode, $assignedTo, $escalatedTo, $status, $priority, $messages]) {
            if (empty($patients[$patientCode])) {
                continue;
            }
            $patient = DB::table('patients')->where('id', $patients[$patientCode])->first();
            $branchId = $branchIds[$branchCode] ?? reset($branchIds);
            $last = end($messages);

            DB::table('whatsapp_conversations')->updateOrInsert(
                ['conversation_number' => $number],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'patient_id' => $patient->id,
                    'assigned_to' => $assignedTo,
                    'escalated_to' => $escalatedTo,
                    'whatsapp_number' => $patient->whatsapp_number,
                    'contact_name' => $patient->full_name,
                    'subject' => 'Patient service conversation',
                    'status' => $status,
                    'priority' => $priority,
                    'unread_count' => collect($messages)->where(0, 'in')->count(),
                    'last_message_preview' => $last[2],
                    'last_message_at' => $last[4],
                    'escalated_at' => $escalatedTo ? $now->copy()->subHours(3) : null,
                    'tags' => json_encode(['demo', $branchCode]),
                    'context_snapshot' => json_encode(['patient_code' => $patientCode]),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $conversationId = (int) DB::table('whatsapp_conversations')->where('conversation_number', $number)->value('id');
            foreach ($messages as [$direction, $templateKey, $body, $messageStatus, $at]) {
                $providerId = 'demo-'.$number.'-'.Str::slug(Str::limit($body, 16, ''));
                DB::table('patient_whatsapp_messages')->updateOrInsert(
                    ['provider_message_id' => $providerId],
                    [
                        'patient_id' => $patient->id,
                        'whatsapp_conversation_id' => $conversationId,
                        'branch_id' => $branchId,
                        'assigned_to' => $assignedTo,
                        'whatsapp_template_id' => $templateKey ? ($templates[$templateKey] ?? null) : null,
                        'direction' => $direction,
                        'whatsapp_number' => $patient->whatsapp_number,
                        'message' => $body,
                        'message_type' => $templateKey ? 'template' : 'text',
                        'context_type' => $templateKey && str_contains($templateKey, 'appointment') ? 'patient_appointment' : null,
                        'status' => $messageStatus,
                        'queued_at' => $direction === 'out' ? $at->copy()->subMinute() : null,
                        'sent_at' => $direction === 'out' && $messageStatus !== 'failed' ? $at : null,
                        'delivered_at' => in_array($messageStatus, ['delivered', 'read'], true) ? $at->copy()->addMinute() : null,
                        'read_at' => $messageStatus === 'read' ? $at->copy()->addMinutes(2) : null,
                        'failed_at' => $messageStatus === 'failed' ? $at : null,
                        'reply_at' => $direction === 'in' ? $at : null,
                        'retry_count' => $messageStatus === 'failed' ? 1 : 0,
                        'error_code' => $messageStatus === 'failed' ? 'demo_delivery_failed' : null,
                        'error_message' => $messageStatus === 'failed' ? 'Demo failed delivery for retry workflow.' : null,
                        'metadata' => json_encode(['demo' => true]),
                        'updated_at' => $now,
                        'created_at' => $at,
                    ],
                );

                $messageId = (int) DB::table('patient_whatsapp_messages')->where('provider_message_id', $providerId)->value('id');
                DB::table('patient_timeline_events')->updateOrInsert(
                    ['source_type' => 'patient_whatsapp_message', 'source_id' => $messageId, 'event_type' => 'whatsapp'],
                    [
                        'patient_id' => $patient->id,
                        'branch_id' => $branchId,
                        'user_id' => $assignedTo,
                        'event_title' => 'WhatsApp '.$messageStatus,
                        'event_at' => $at,
                        'description' => $body,
                        'metadata' => json_encode(['conversation_id' => $conversationId]),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }

            if ($escalatedTo) {
                DB::table('whatsapp_internal_notes')->updateOrInsert(
                    ['whatsapp_conversation_id' => $conversationId, 'note' => 'Escalated for balance confirmation and payment link approval.'],
                    [
                        'user_id' => $staffId,
                        'visibility' => 'manager',
                        'is_escalation' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }

        $this->linkLegacyMessages($companyId, $branchIds, $now);
    }

    private function linkLegacyMessages(int $companyId, array $branchIds, $now): void
    {
        $messages = DB::table('patient_whatsapp_messages')
            ->whereNull('whatsapp_conversation_id')
            ->limit(100)
            ->get();

        foreach ($messages as $message) {
            $patient = DB::table('patients')->where('id', $message->patient_id)->first();
            if (! $patient) {
                continue;
            }
            $number = $message->whatsapp_number ?: $patient->whatsapp_number ?: $patient->phone;
            if (! $number) {
                continue;
            }
            $conversation = DB::table('whatsapp_conversations')
                ->where('patient_id', $patient->id)
                ->orderByDesc('id')
                ->first();

            if (! $conversation) {
                $conversationId = DB::table('whatsapp_conversations')->insertGetId([
                    'company_id' => $companyId,
                    'branch_id' => $branchIds['MAIN'] ?? reset($branchIds),
                    'patient_id' => $patient->id,
                    'conversation_number' => 'WAC-LEGACY-'.$message->id,
                    'whatsapp_number' => $number,
                    'contact_name' => $patient->full_name,
                    'subject' => 'Imported patient WhatsApp history',
                    'status' => 'resolved',
                    'priority' => 'normal',
                    'last_message_preview' => $message->message,
                    'last_message_at' => $message->sent_at ?: $message->created_at,
                    'resolved_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            } else {
                $conversationId = (int) $conversation->id;
            }

            DB::table('patient_whatsapp_messages')->where('id', $message->id)->update([
                'whatsapp_conversation_id' => $conversationId,
                'branch_id' => $branchIds['MAIN'] ?? reset($branchIds),
                'message_type' => 'text',
                'queued_at' => $message->direction === 'out' ? ($message->sent_at ?: $message->created_at) : null,
                'updated_at' => $now,
            ]);
        }
    }

    private function webhookSamples($now): void
    {
        DB::table('whatsapp_webhook_events')->updateOrInsert(
            ['event_uid' => 'demo-status-delivered'],
            [
                'provider' => 'meta_cloud',
                'event_type' => 'status',
                'phone_number_id' => 'sandbox-phone-number-id',
                'whatsapp_number' => '+966500111222',
                'provider_message_id' => 'demo-WAC-1001-hello-sara-ahmed',
                'payload' => json_encode(['statuses' => [['status' => 'delivered']]]),
                'status' => 'processed',
                'processed_at' => $now->copy()->subHours(18),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function variables(string $body): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function permissionsAndRoles(int $companyId, $now): void
    {
        foreach (config('whatsapp.permissions') as $permission) {
            [$module, $area, $action] = array_pad(explode('.', $permission), 3, null);
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission],
                [
                    'module' => $module.'.'.$area,
                    'action' => $action ?: 'access',
                    'name' => str($permission)->replace('.', ' ')->title(),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        foreach (config('whatsapp.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'WhatsApp Messaging module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
