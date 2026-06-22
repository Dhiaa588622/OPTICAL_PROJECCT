<?php

namespace App\Http\Controllers;

use App\Support\SetupOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    private const PAGES = [
        'inbox' => 'WhatsApp Inbox',
        'conversation' => 'Conversation Details',
        'patient-panel' => 'Patient Chat Panel',
        'templates' => 'Template Manager',
        'automations' => 'Automation Rules',
        'consent' => 'Opt-in / Opt-out',
        'logs' => 'Message Logs',
        'failed' => 'Failed Messages',
        'settings' => 'WhatsApp Settings',
        'reports' => 'Reports',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'inbox';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasTable('patient_whatsapp_messages')) {
            return view('whatsapp.app', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('whatsapp.app', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->whatsappData($request),
        ]);
    }

    public function meta()
    {
        return response()->json([
            'module' => config('whatsapp.module'),
            'provider' => [
                'name' => config('whatsapp.provider.name'),
                'base_url' => config('whatsapp.provider.base_url'),
                'api_version' => config('whatsapp.provider.api_version'),
                'mode' => $this->settings()->mode ?? config('whatsapp.provider.mode'),
            ],
            'conversation_statuses' => config('whatsapp.conversation_statuses'),
            'message_statuses' => config('whatsapp.message_statuses'),
            'message_types' => config('whatsapp.message_types'),
            'template_keys' => config('whatsapp.template_keys'),
            'automation_triggers' => config('whatsapp.automation_triggers'),
            'content_policy' => config('whatsapp.content_policy'),
            'permissions' => config('whatsapp.permissions'),
            'roles' => config('whatsapp.roles'),
            'reports' => config('whatsapp.reports'),
            'integration_rules' => config('whatsapp.integration_rules'),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $request->integer('branch_id') ?: null;

        return response()->json([
            'branch_id' => $branchId,
            'business_date' => now()->toDateString(),
            'metrics' => $this->metrics($branchId),
            'open_conversations' => $this->conversations($branchId, '', 'open', null),
            'failed_messages' => $this->messages($branchId, '', 'failed', null, 15),
            'settings' => $this->publicSettings(),
        ]);
    }

    public function conversationsApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'query' => $q,
            'searchable_fields' => ['patient_name', 'phone', 'whatsapp_number', 'order_number', 'invoice_number', 'conversation_number'],
            'data' => $this->conversations(
                $request->integer('branch_id') ?: null,
                $q,
                $request->query('status'),
                $request->integer('assigned_to') ?: null,
            ),
        ]);
    }

    public function showApi(int $conversation)
    {
        $details = $this->conversationDetails($conversation);
        if (! $details) {
            abort(404);
        }

        return response()->json($details);
    }

    public function logsApi(Request $request)
    {
        return response()->json([
            'query' => trim((string) $request->query('q', '')),
            'status' => $request->query('status'),
            'data' => $this->messages(
                $request->integer('branch_id') ?: null,
                trim((string) $request->query('q', '')),
                $request->query('status'),
                $request->integer('patient_id') ?: null,
                100,
            ),
        ]);
    }

    public function reportApi(Request $request, string $report)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $data = match ($report) {
            'total-messages-sent' => DB::table('patient_whatsapp_messages')
                ->selectRaw('date(coalesce(sent_at, created_at)) as message_date, count(*) as messages')
                ->where('direction', 'out')
                ->whereIn('status', ['sent', 'delivered', 'read', 'replied'])
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereBetween(DB::raw('date(coalesce(sent_at, created_at))'), [$dateFrom, $dateTo])
                ->groupBy(DB::raw('date(coalesce(sent_at, created_at))'))
                ->orderBy('message_date')
                ->get(),
            'failed-messages' => $this->messages($branchId, '', 'failed', null, 100),
            'appointment-reminders-sent' => DB::table('patient_whatsapp_messages')
                ->join('patients', 'patients.id', '=', 'patient_whatsapp_messages.patient_id')
                ->select('patient_whatsapp_messages.*', 'patients.patient_code', 'patients.full_name as patient_name')
                ->where('patient_whatsapp_messages.direction', 'out')
                ->where('patient_whatsapp_messages.context_type', 'patient_appointment')
                ->when($branchId, fn ($query) => $query->where('patient_whatsapp_messages.branch_id', $branchId))
                ->whereBetween(DB::raw('date(coalesce(patient_whatsapp_messages.sent_at, patient_whatsapp_messages.created_at))'), [$dateFrom, $dateTo])
                ->orderByDesc('patient_whatsapp_messages.id')
                ->get(),
            'order-ready-messages-sent' => DB::table('patient_whatsapp_messages')
                ->join('patients', 'patients.id', '=', 'patient_whatsapp_messages.patient_id')
                ->select('patient_whatsapp_messages.*', 'patients.patient_code', 'patients.full_name as patient_name')
                ->where('patient_whatsapp_messages.direction', 'out')
                ->where('patient_whatsapp_messages.context_type', 'optical_order')
                ->where('patient_whatsapp_messages.message', 'like', '%ready%')
                ->when($branchId, fn ($query) => $query->where('patient_whatsapp_messages.branch_id', $branchId))
                ->whereBetween(DB::raw('date(coalesce(patient_whatsapp_messages.sent_at, patient_whatsapp_messages.created_at))'), [$dateFrom, $dateTo])
                ->orderByDesc('patient_whatsapp_messages.id')
                ->get(),
            'response-rate' => collect([
                [
                    'outbound_messages' => DB::table('patient_whatsapp_messages')
                        ->where('direction', 'out')
                        ->whereBetween(DB::raw('date(coalesce(sent_at, created_at))'), [$dateFrom, $dateTo])
                        ->count(),
                    'inbound_replies' => DB::table('patient_whatsapp_messages')
                        ->where('direction', 'in')
                        ->whereBetween(DB::raw('date(created_at)'), [$dateFrom, $dateTo])
                        ->count(),
                    'response_rate_percent' => $this->responseRate($dateFrom, $dateTo),
                ],
            ]),
            'conversations-by-staff' => DB::table('whatsapp_conversations')
                ->leftJoin('users', 'users.id', '=', 'whatsapp_conversations.assigned_to')
                ->selectRaw('coalesce(users.name, "Unassigned") as staff_name, count(*) as conversations')
                ->when($branchId, fn ($query) => $query->where('whatsapp_conversations.branch_id', $branchId))
                ->whereBetween(DB::raw('date(whatsapp_conversations.created_at)'), [$dateFrom, $dateTo])
                ->groupBy('users.name')
                ->orderByDesc('conversations')
                ->get(),
            'unresolved-conversations' => $this->conversations($branchId, '', null, null)
                ->filter(fn ($conversation) => $conversation->status !== 'resolved')
                ->values(),
            'opt-out' => DB::table('whatsapp_consent_events')
                ->leftJoin('patients', 'patients.id', '=', 'whatsapp_consent_events.patient_id')
                ->select('whatsapp_consent_events.*', 'patients.patient_code', 'patients.full_name as patient_name')
                ->where('whatsapp_consent_events.consent_status', 'opt_out')
                ->whereBetween(DB::raw('date(whatsapp_consent_events.recorded_at)'), [$dateFrom, $dateTo])
                ->orderByDesc('whatsapp_consent_events.recorded_at')
                ->get(),
            default => abort(404),
        };

        return response()->json([
            'report' => $report,
            'filters' => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'data' => $data,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'patient_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:4000'],
            'message_type' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'context_id' => ['nullable', 'integer'],
            'send_mode' => ['nullable', 'string', 'max:255'],
            'override_opt_out' => ['nullable'],
        ]);

        $messageId = DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $conversation = $this->resolveConversation($validated, $now);
            $patient = DB::table('patients')->where('id', $conversation->patient_id)->first();
            $sendMode = $validated['send_mode'] ?? 'manual';

            $this->assertCanMessage($patient, $sendMode, $request->boolean('override_opt_out'));

            $template = ! empty($validated['template_id'])
                ? DB::table('whatsapp_templates')->where('id', $validated['template_id'])->first()
                : null;
            $body = trim((string) ($validated['message'] ?? ''));
            if ($template && $body === '') {
                $body = $this->renderTemplate($template, $patient, $validated);
            }
            if ($body === '') {
                throw ValidationException::withMessages(['message' => 'Write a message or choose a template.']);
            }

            $body = $this->applyContentPolicy($body, $template, $patient);
            $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
                'patient_id' => $patient->id,
                'whatsapp_conversation_id' => $conversation->id,
                'branch_id' => $validated['branch_id'] ?? $conversation->branch_id,
                'assigned_to' => $validated['assigned_to'] ?? $conversation->assigned_to,
                'whatsapp_template_id' => $template?->id,
                'direction' => 'out',
                'whatsapp_number' => $conversation->whatsapp_number,
                'message' => $body,
                'message_type' => $validated['message_type'] ?? ($template ? 'template' : 'text'),
                'context_type' => $validated['context_type'] ?? null,
                'context_id' => $validated['context_id'] ?? null,
                'status' => 'queued',
                'queued_at' => $now,
                'metadata' => json_encode(['send_mode' => $sendMode]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->dispatchMessage($messageId);
            $fresh = DB::table('patient_whatsapp_messages')->where('id', $messageId)->first();
            $this->touchConversation((int) $conversation->id, $body, $fresh->sent_at ?: $now, false, $fresh->status);
            $this->timeline((int) $patient->id, $conversation->branch_id, $validated['assigned_to'] ?? null, 'whatsapp', 'WhatsApp message sent', $fresh->sent_at ?: $now, $body, 'patient_whatsapp_message', $messageId, ['conversation_id' => $conversation->id]);
            $this->audit('whatsapp.message.sent', 'patient_whatsapp_message', $messageId, $conversation->branch_id, null, ['status' => $fresh->status, 'conversation_id' => $conversation->id], $now);

            return $messageId;
        });

        return $this->respond($request, ['status' => 'sent', 'message_id' => $messageId], 'conversation', 'WhatsApp message queued and saved.');
    }

    public function updateConversation(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'status' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer'],
            'escalated_to' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $conversation = DB::table('whatsapp_conversations')->where('id', $validated['conversation_id'])->first();
            if (! $conversation) {
                throw ValidationException::withMessages(['conversation_id' => 'Conversation was not found.']);
            }

            $updates = ['updated_at' => $now];
            foreach (['status', 'priority', 'assigned_to'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== null && $validated[$field] !== '') {
                    $updates[$field] = $validated[$field];
                }
            }
            if (! empty($validated['escalated_to'])) {
                $updates['escalated_to'] = $validated['escalated_to'];
                $updates['escalated_at'] = $now;
                $updates['priority'] = $updates['priority'] ?? 'urgent';
            }
            if (($updates['status'] ?? null) === 'resolved') {
                $updates['resolved_at'] = $now;
                $updates['unread_count'] = 0;
            }

            DB::table('whatsapp_conversations')->where('id', $conversation->id)->update($updates);

            if (! empty($validated['note'])) {
                DB::table('whatsapp_internal_notes')->insert([
                    'whatsapp_conversation_id' => $conversation->id,
                    'user_id' => $validated['user_id'] ?? null,
                    'note' => $validated['note'],
                    'visibility' => ! empty($validated['escalated_to']) ? 'manager' : 'internal',
                    'is_escalation' => ! empty($validated['escalated_to']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit('whatsapp.conversation.updated', 'whatsapp_conversation', (int) $conversation->id, $conversation->branch_id, (array) $conversation, $updates, $now);
        });

        return $this->respond($request, ['status' => 'updated', 'conversation_id' => $validated['conversation_id']], 'conversation', 'Conversation updated.');
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'trigger_key' => ['nullable', 'string', 'max:255'],
            'provider_template_name' => ['nullable', 'string', 'max:255'],
            'language_code' => ['required', 'string', 'max:20'],
            'body' => ['required', 'string', 'max:4000'],
            'footer' => ['nullable', 'string', 'max:255'],
            'send_sensitive_details' => ['nullable'],
            'is_active' => ['nullable'],
        ]);

        $now = now();
        $id = $validated['template_id'] ?? null;
        $payload = [
            'company_id' => $this->companyId(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'category' => $validated['category'],
            'trigger_key' => $validated['trigger_key'] ?? null,
            'provider_template_name' => $validated['provider_template_name'] ?? null,
            'language_code' => $validated['language_code'],
            'body' => $validated['body'],
            'footer' => $validated['footer'] ?? null,
            'variables' => json_encode($this->extractVariables($validated['body'])),
            'send_sensitive_details' => $request->boolean('send_sensitive_details'),
            'is_active' => $request->boolean('is_active', true),
            'updated_at' => $now,
        ];

        if ($id) {
            DB::table('whatsapp_templates')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('whatsapp_templates')->insert($payload);
        }

        return $this->respond($request, ['status' => 'saved'], 'templates', 'Template saved.');
    }

    public function saveAutomation(Request $request)
    {
        $validated = $request->validate([
            'automation_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'trigger_key' => ['required', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'max:255'],
            'timing' => ['required', 'string', 'max:255'],
            'offset_minutes' => ['nullable', 'integer'],
            'requires_opt_in' => ['nullable'],
        ]);

        $now = now();
        $id = $validated['automation_id'] ?? null;
        $payload = [
            'company_id' => $this->companyId(),
            'branch_id' => $validated['branch_id'] ?? null,
            'whatsapp_template_id' => $validated['template_id'] ?? null,
            'name' => $validated['name'],
            'trigger_key' => $validated['trigger_key'],
            'status' => $validated['status'],
            'timing' => $validated['timing'],
            'offset_minutes' => (int) ($validated['offset_minutes'] ?? 0),
            'requires_opt_in' => $request->boolean('requires_opt_in', true),
            'updated_at' => $now,
        ];

        if ($id) {
            DB::table('whatsapp_automation_rules')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('whatsapp_automation_rules')->insert($payload);
        }

        return $this->respond($request, ['status' => 'saved'], 'automations', 'Automation rule saved.');
    }

    public function updateConsent(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'consent_status' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'policy_version' => ['nullable', 'string', 'max:255'],
            'recorded_by' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $patient = ! empty($validated['patient_id'])
                ? DB::table('patients')->where('id', $validated['patient_id'])->first()
                : null;
            $number = $validated['whatsapp_number'] ?? $patient?->whatsapp_number;
            if (! $number) {
                throw ValidationException::withMessages(['whatsapp_number' => 'WhatsApp number is required.']);
            }

            $isOptIn = $validated['consent_status'] === 'opt_in';
            if ($patient) {
                DB::table('patients')->where('id', $patient->id)->update([
                    'whatsapp_number' => $number,
                    'whatsapp_opt_in' => $isOptIn,
                    'whatsapp_opt_out' => ! $isOptIn,
                    'whatsapp_consent_at' => $now,
                    'whatsapp_consent_source' => $validated['source'] ?? 'manual',
                    'whatsapp_content_policy' => $validated['policy_version'] ?? 'standard',
                    'updated_at' => $now,
                ]);
            }

            DB::table('whatsapp_consent_events')->insert([
                'patient_id' => $patient?->id,
                'whatsapp_number' => $number,
                'consent_status' => $validated['consent_status'],
                'source' => $validated['source'] ?? 'manual',
                'policy_version' => $validated['policy_version'] ?? 'standard',
                'recorded_by' => $validated['recorded_by'] ?? null,
                'recorded_at' => $now,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($patient) {
                $this->timeline((int) $patient->id, null, $validated['recorded_by'] ?? null, 'whatsapp', 'WhatsApp consent '.$validated['consent_status'], $now, $validated['notes'] ?? 'Consent updated from WhatsApp module.', 'whatsapp_consent_event', (int) DB::getPdo()->lastInsertId());
            }
        });

        return $this->respond($request, ['status' => 'updated'], 'consent', 'WhatsApp consent updated.');
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'max:255'],
            'api_version' => ['required', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'app_secret' => ['nullable', 'string', 'max:4000'],
            'content_policy' => ['required', 'string', 'max:255'],
            'auto_send_enabled' => ['nullable'],
            'media_allowed' => ['nullable'],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $now = now();
        $existing = $this->settings();
        $token = $validated['access_token'] ?? null;
        $appSecret = $validated['app_secret'] ?? null;
        $payload = [
            'company_id' => $this->companyId(),
            'provider' => 'meta_cloud',
            'mode' => $validated['mode'],
            'api_version' => $validated['api_version'],
            'phone_number_id' => $validated['phone_number_id'] ?? null,
            'business_account_id' => $validated['business_account_id'] ?? null,
            'webhook_verify_token' => $validated['webhook_verify_token'] ?? null,
            'content_policy' => $validated['content_policy'],
            'auto_send_enabled' => $request->boolean('auto_send_enabled'),
            'media_allowed' => $request->boolean('media_allowed'),
            'retry_limit' => (int) $validated['retry_limit'],
            'status' => 'active',
            'updated_at' => $now,
        ];
        if ($token) {
            $payload['access_token_masked'] = Str::mask($token, '*', 6, max(0, strlen($token) - 10));
            $payload['access_token_ciphertext'] = encrypt($token);
        }
        if ($appSecret) {
            $payload['app_secret'] = encrypt($appSecret);
        }

        if ($existing) {
            DB::table('whatsapp_settings')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('whatsapp_settings')->insert($payload);
        }

        return $this->respond($request, ['status' => 'saved'], 'settings', 'WhatsApp settings saved.');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        $settings = $this->settings();
        $token = $this->accessToken($settings);
        $phoneNumberId = $settings?->phone_number_id ?: config('whatsapp.provider.phone_number_id');
        $apiVersion = $settings?->api_version ?: config('whatsapp.provider.api_version');

        if (! $token || ! $phoneNumberId) {
            throw ValidationException::withMessages([
                'connection' => 'Save a Meta access token and phone number ID before testing the connection.',
            ]);
        }

        $url = rtrim(config('whatsapp.provider.base_url'), '/').'/'.$apiVersion.'/'.$phoneNumberId;
        $response = Http::withToken($token)->acceptJson()->get($url, [
            'fields' => 'display_phone_number,verified_name,quality_rating',
        ]);
        $body = $response->json();

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'connection' => $body['error']['message'] ?? 'Meta rejected the WhatsApp connection details.',
            ]);
        }

        $displayNumber = $body['display_phone_number'] ?? $phoneNumberId;
        $verifiedName = $body['verified_name'] ?? 'WhatsApp Business';

        return redirect()->route('whatsapp.app', ['page' => 'settings', 'lang' => app()->getLocale()])
            ->with('status', "Connected to {$verifiedName} ({$displayNumber}).");
    }

    public function retryFailed(Request $request)
    {
        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
        ]);

        $message = DB::table('patient_whatsapp_messages')->where('id', $validated['message_id'])->first();
        if (! $message || $message->status !== 'failed') {
            throw ValidationException::withMessages(['message_id' => 'Failed message was not found.']);
        }

        $settings = $this->settings();
        if ((int) $message->retry_count >= (int) ($settings->retry_limit ?? 3)) {
            throw ValidationException::withMessages(['message_id' => 'Retry limit reached.']);
        }

        DB::table('patient_whatsapp_messages')->where('id', $message->id)->update([
            'status' => 'queued',
            'queued_at' => now(),
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
            'retry_count' => (int) $message->retry_count + 1,
            'updated_at' => now(),
        ]);

        $this->dispatchMessage((int) $message->id);

        return $this->respond($request, ['status' => 'retried', 'message_id' => $message->id], 'failed', 'Failed message retried.');
    }

    public function automationTrigger(Request $request)
    {
        $validated = $request->validate([
            'trigger_key' => ['required', 'string', 'max:255'],
            'patient_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $rule = DB::table('whatsapp_automation_rules')
            ->where('trigger_key', $validated['trigger_key'])
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $rule) {
            return response()->json(['status' => 'skipped', 'reason' => 'No active automation rule found.'], 202);
        }

        $messageId = $this->sendAutomationMessage($rule, $validated);

        return response()->json(['status' => 'queued', 'message_id' => $messageId]);
    }

    public function verifyWebhook(Request $request)
    {
        $token = $this->settings()->webhook_verify_token ?? config('whatsapp.provider.webhook_verify_token');
        if ($request->query('hub_mode') === 'subscribe' || $request->query('hub.mode') === 'subscribe') {
            $verifyToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
            if ($verifyToken && hash_equals((string) $token, (string) $verifyToken)) {
                return response($request->query('hub_challenge', $request->query('hub.challenge')), 200);
            }
        }

        abort(403);
    }

    public function incomingWebhook(Request $request)
    {
        $settings = $this->settings();
        $mode = $settings?->mode ?? config('whatsapp.provider.mode', 'sandbox');
        $appSecret = $this->appSecret($settings);

        if ($appSecret) {
            $signature = (string) $request->header('X-Hub-Signature-256');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);
            abort_unless($signature !== '' && hash_equals($expected, $signature), 403);
        } elseif ($mode === 'live') {
            abort(503, 'WhatsApp app secret is not configured.');
        }

        $payload = $request->all();
        $processed = ['messages' => 0, 'statuses' => 0, 'duplicates' => 0];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['statuses'] ?? []) as $status) {
                    $processed[$this->processStatusWebhook($status, $value) ? 'statuses' : 'duplicates']++;
                }
                foreach (($value['messages'] ?? []) as $message) {
                    $processed[$this->processIncomingMessageWebhook($message, $value) ? 'messages' : 'duplicates']++;
                }
            }
        }

        return response()->json(['status' => 'processed', 'processed' => $processed]);
    }

    private function whatsappData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $conversationId = $request->integer('conversation_id') ?: (int) (DB::table('whatsapp_conversations')->orderByDesc('last_message_at')->value('id') ?? 0);

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'statusFilter' => $status,
            'conversationStatuses' => config('whatsapp.conversation_statuses'),
            'conversationPriorities' => config('whatsapp.conversation_priorities'),
            'messageStatuses' => config('whatsapp.message_statuses'),
            'messageTypes' => config('whatsapp.message_types'),
            'templateCategories' => app(SetupOptions::class)->options('whatsapp_template_categories'),
            'templateKeys' => config('whatsapp.template_keys'),
            'automationTriggers' => config('whatsapp.automation_triggers'),
            'contentPolicies' => config('whatsapp.content_policy.policies'),
            'reports' => config('whatsapp.reports'),
            'metrics' => $this->metrics($branchId),
            'conversations' => $this->conversations($branchId, $query, $status, $request->integer('assigned_to') ?: null),
            'selectedConversation' => $conversationId ? $this->conversationDetails($conversationId) : null,
            'patients' => $this->patients($query),
            'staff' => DB::table('users')->orderBy('name')->get(),
            'templates' => DB::table('whatsapp_templates')->orderBy('trigger_key')->orderBy('name')->get(),
            'automationRules' => DB::table('whatsapp_automation_rules')
                ->leftJoin('whatsapp_templates', 'whatsapp_templates.id', '=', 'whatsapp_automation_rules.whatsapp_template_id')
                ->select('whatsapp_automation_rules.*', 'whatsapp_templates.name as template_name')
                ->orderBy('whatsapp_automation_rules.trigger_key')
                ->get(),
            'messages' => $this->messages($branchId, $query, $status, null, 100),
            'failedMessages' => $this->messages($branchId, $query, 'failed', null, 100),
            'consentEvents' => DB::table('whatsapp_consent_events')
                ->leftJoin('patients', 'patients.id', '=', 'whatsapp_consent_events.patient_id')
                ->select('whatsapp_consent_events.*', 'patients.patient_code', 'patients.full_name as patient_name')
                ->orderByDesc('whatsapp_consent_events.recorded_at')
                ->limit(80)
                ->get(),
            'settings' => $this->settings(),
            'publicSettings' => $this->publicSettings(),
            'reportData' => $this->reportPreview($branchId),
        ];
    }

    private function metrics(?int $branchId): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        return [
            'open_conversations' => DB::table('whatsapp_conversations')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'open')->count(),
            'pending_conversations' => DB::table('whatsapp_conversations')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'pending')->count(),
            'unread_messages' => DB::table('whatsapp_conversations')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->sum('unread_count'),
            'sent_month' => DB::table('patient_whatsapp_messages')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('direction', 'out')->whereDate('created_at', '>=', $monthStart)->count(),
            'failed_month' => DB::table('patient_whatsapp_messages')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'failed')->whereDate('created_at', '>=', $monthStart)->count(),
            'opt_outs' => DB::table('patients')->where('whatsapp_opt_out', true)->count(),
            'response_rate' => $this->responseRate($monthStart, now()->toDateString()),
        ];
    }

    private function conversations(?int $branchId, string $query, ?string $status, ?int $assignedTo)
    {
        return DB::table('whatsapp_conversations')
            ->leftJoin('patients', 'patients.id', '=', 'whatsapp_conversations.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'whatsapp_conversations.branch_id')
            ->leftJoin('users', 'users.id', '=', 'whatsapp_conversations.assigned_to')
            ->select([
                'whatsapp_conversations.*',
                'patients.patient_code',
                'patients.full_name as patient_name',
                'patients.phone',
                'patients.whatsapp_opt_in',
                'patients.whatsapp_opt_out',
                'branches.name as branch_name',
                'users.name as assigned_to_name',
            ])
            ->when($branchId, fn ($builder) => $builder->where('whatsapp_conversations.branch_id', $branchId))
            ->when($status, fn ($builder) => $builder->where('whatsapp_conversations.status', $status))
            ->when($assignedTo, fn ($builder) => $builder->where('whatsapp_conversations.assigned_to', $assignedTo))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('whatsapp_conversations.conversation_number', 'like', "%{$query}%")
                        ->orWhere('whatsapp_conversations.whatsapp_number', 'like', "%{$query}%")
                        ->orWhere('whatsapp_conversations.contact_name', 'like', "%{$query}%")
                        ->orWhere('whatsapp_conversations.subject', 'like', "%{$query}%")
                        ->orWhere('patients.full_name', 'like', "%{$query}%")
                        ->orWhere('patients.patient_code', 'like', "%{$query}%")
                        ->orWhere('patients.phone', 'like', "%{$query}%")
                        ->orWhereExists(function ($sub) use ($query): void {
                            $sub->selectRaw('1')
                                ->from('patient_whatsapp_messages')
                                ->whereColumn('patient_whatsapp_messages.whatsapp_conversation_id', 'whatsapp_conversations.id')
                                ->where('patient_whatsapp_messages.message', 'like', "%{$query}%");
                        });
                    if (Schema::hasTable('optical_orders')) {
                        $inner->orWhereExists(function ($sub) use ($query): void {
                            $sub->selectRaw('1')
                                ->from('optical_orders')
                                ->whereColumn('optical_orders.patient_id', 'whatsapp_conversations.patient_id')
                                ->where('optical_orders.order_number', 'like', "%{$query}%");
                        });
                    }
                    if (Schema::hasTable('sales_invoices')) {
                        $inner->orWhereExists(function ($sub) use ($query): void {
                            $sub->selectRaw('1')
                                ->from('sales_invoices')
                                ->join('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                                ->whereColumn('sales_customers.patient_id', 'whatsapp_conversations.patient_id')
                                ->where('sales_invoices.invoice_number', 'like', "%{$query}%");
                        });
                    }
                });
            })
            ->orderByDesc('whatsapp_conversations.last_message_at')
            ->orderByDesc('whatsapp_conversations.id')
            ->limit(120)
            ->get();
    }

    private function conversationDetails(int $conversationId): ?array
    {
        $conversation = $this->conversations(null, '', null, null)->first(fn ($item) => (int) $item->id === $conversationId);
        if (! $conversation) {
            return null;
        }

        DB::table('whatsapp_conversations')->where('id', $conversation->id)->update(['unread_count' => 0, 'updated_at' => now()]);
        $patient = $conversation->patient_id ? DB::table('patients')->where('id', $conversation->patient_id)->first() : null;

        return [
            'conversation' => $conversation,
            'messages' => DB::table('patient_whatsapp_messages')
                ->leftJoin('users', 'users.id', '=', 'patient_whatsapp_messages.assigned_to')
                ->leftJoin('whatsapp_templates', 'whatsapp_templates.id', '=', 'patient_whatsapp_messages.whatsapp_template_id')
                ->select('patient_whatsapp_messages.*', 'users.name as staff_name', 'whatsapp_templates.name as template_name')
                ->where('patient_whatsapp_messages.whatsapp_conversation_id', $conversationId)
                ->orderBy('patient_whatsapp_messages.created_at')
                ->orderBy('patient_whatsapp_messages.id')
                ->get(),
            'notes' => DB::table('whatsapp_internal_notes')
                ->leftJoin('users', 'users.id', '=', 'whatsapp_internal_notes.user_id')
                ->select('whatsapp_internal_notes.*', 'users.name as user_name')
                ->where('whatsapp_conversation_id', $conversationId)
                ->orderByDesc('whatsapp_internal_notes.id')
                ->get(),
            'patient' => $patient,
            'appointments' => $patient && Schema::hasTable('patient_appointments')
                ? DB::table('patient_appointments')->where('patient_id', $patient->id)->orderByDesc('appointment_at')->limit(5)->get()
                : collect(),
            'orders' => $patient && Schema::hasTable('optical_orders')
                ? DB::table('optical_orders')->where('patient_id', $patient->id)->orderByDesc('order_date')->limit(5)->get()
                : collect(),
            'invoices' => $patient && Schema::hasTable('sales_invoices')
                ? DB::table('sales_invoices')
                    ->join('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                    ->select('sales_invoices.*')
                    ->where('sales_customers.patient_id', $patient->id)
                    ->orderByDesc('sales_invoices.invoice_date')
                    ->limit(5)
                    ->get()
                : collect(),
            'balance' => $patient && Schema::hasTable('sales_invoices')
                ? (float) DB::table('sales_invoices')
                    ->join('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                    ->where('sales_customers.patient_id', $patient->id)
                    ->sum('sales_invoices.balance_due')
                : 0,
            'timeline' => $patient && Schema::hasTable('patient_timeline_events')
                ? DB::table('patient_timeline_events')->where('patient_id', $patient->id)->orderByDesc('event_at')->limit(8)->get()
                : collect(),
        ];
    }

    private function messages(?int $branchId, string $query, ?string $status, ?int $patientId, int $limit)
    {
        return DB::table('patient_whatsapp_messages')
            ->join('patients', 'patients.id', '=', 'patient_whatsapp_messages.patient_id')
            ->leftJoin('whatsapp_conversations', 'whatsapp_conversations.id', '=', 'patient_whatsapp_messages.whatsapp_conversation_id')
            ->leftJoin('users', 'users.id', '=', 'patient_whatsapp_messages.assigned_to')
            ->select('patient_whatsapp_messages.*', 'patients.patient_code', 'patients.full_name as patient_name', 'whatsapp_conversations.conversation_number', 'users.name as staff_name')
            ->when($branchId, fn ($builder) => $builder->where('patient_whatsapp_messages.branch_id', $branchId))
            ->when($status, fn ($builder) => $builder->where('patient_whatsapp_messages.status', $status))
            ->when($patientId, fn ($builder) => $builder->where('patient_whatsapp_messages.patient_id', $patientId))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('patient_whatsapp_messages.message', 'like', "%{$query}%")
                        ->orWhere('patient_whatsapp_messages.whatsapp_number', 'like', "%{$query}%")
                        ->orWhere('patient_whatsapp_messages.provider_message_id', 'like', "%{$query}%")
                        ->orWhere('patients.full_name', 'like', "%{$query}%")
                        ->orWhere('patients.patient_code', 'like', "%{$query}%")
                        ->orWhere('whatsapp_conversations.conversation_number', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('patient_whatsapp_messages.created_at')
            ->limit($limit)
            ->get();
    }

    private function patients(string $query)
    {
        return DB::table('patients')
            ->select('id', 'patient_code', 'full_name', 'phone', 'whatsapp_number', 'whatsapp_opt_in', 'whatsapp_opt_out')
            ->where('is_active', true)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('full_name', 'like', "%{$query}%")
                        ->orWhere('patient_code', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('whatsapp_number', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('id')
            ->limit(120)
            ->get();
    }

    private function resolveConversation(array $validated, $now): object
    {
        if (! empty($validated['conversation_id'])) {
            $conversation = DB::table('whatsapp_conversations')->where('id', $validated['conversation_id'])->first();
            if ($conversation) {
                return $conversation;
            }
        }

        $patient = ! empty($validated['patient_id'])
            ? DB::table('patients')->where('id', $validated['patient_id'])->first()
            : null;
        $number = $validated['whatsapp_number'] ?? $patient?->whatsapp_number;
        if (! $number) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Choose a patient or enter a WhatsApp number.']);
        }
        $patient = $patient ?: $this->resolvePatientForNumber($number, $now);

        $existing = DB::table('whatsapp_conversations')
            ->where('whatsapp_number', $number)
            ->where('status', '!=', 'resolved')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $id = DB::table('whatsapp_conversations')->insertGetId([
            'company_id' => $patient->company_id ?? $this->companyId(),
            'branch_id' => $validated['branch_id'] ?? null,
            'patient_id' => $patient->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'conversation_number' => $this->nextNumber('WAC', 'whatsapp_conversations', 'conversation_number'),
            'whatsapp_number' => $number,
            'contact_name' => $patient->full_name,
            'subject' => 'Patient WhatsApp conversation',
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('whatsapp_conversations')->where('id', $id)->first();
    }

    private function resolvePatientForNumber(string $number, $now): object
    {
        $patient = DB::table('patients')->where('whatsapp_number', $number)->orWhere('phone', $number)->first();
        if ($patient) {
            return $patient;
        }

        $id = DB::table('patients')->insertGetId([
            'company_id' => $this->companyId(),
            'patient_code' => $this->nextNumber('WA', 'patients', 'patient_code'),
            'full_name' => 'WhatsApp Contact '.$number,
            'gender' => 'not_specified',
            'phone' => $number,
            'whatsapp_number' => $number,
            'whatsapp_opt_in' => false,
            'whatsapp_opt_out' => false,
            'whatsapp_consent_source' => 'incoming_message',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('patients')->where('id', $id)->first();
    }

    private function assertCanMessage(object $patient, string $sendMode, bool $overrideOptOut): void
    {
        if ((bool) ($patient->whatsapp_opt_out ?? false) && ! $overrideOptOut) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Patient opted out of WhatsApp messages.']);
        }

        if ($sendMode === 'automation' && ! (bool) ($patient->whatsapp_opt_in ?? false)) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Automatic messages require WhatsApp opt-in.']);
        }
    }

    private function dispatchMessage(int $messageId): void
    {
        $message = DB::table('patient_whatsapp_messages')->where('id', $messageId)->first();
        if (! $message) {
            return;
        }

        $settings = $this->settings();
        $now = now();
        $mode = $settings->mode ?? config('whatsapp.provider.mode', 'sandbox');

        if ($mode !== 'live') {
            DB::table('patient_whatsapp_messages')->where('id', $messageId)->update([
                'status' => 'sent',
                'sent_at' => $now,
                'provider_message_id' => $message->provider_message_id ?: 'sandbox-'.$messageId.'-'.Str::lower(Str::random(6)),
                'provider_payload' => json_encode(['mode' => 'sandbox', 'message' => 'Stored locally.']),
                'updated_at' => $now,
            ]);

            return;
        }

        try {
            $token = $this->accessToken($settings);
            $phoneNumberId = $settings->phone_number_id ?? config('whatsapp.provider.phone_number_id');
            if (! $token || ! $phoneNumberId) {
                throw new \RuntimeException('Missing WhatsApp Cloud API token or phone number ID.');
            }

            $url = rtrim(config('whatsapp.provider.base_url'), '/').'/'.($settings->api_version ?? config('whatsapp.provider.api_version')).'/'.$phoneNumberId.'/messages';
            $payload = $this->cloudPayload($message);
            $response = Http::withToken($token)->acceptJson()->post($url, $payload);
            $body = $response->json();

            if (! $response->successful()) {
                throw new \RuntimeException($body['error']['message'] ?? 'WhatsApp Cloud API request failed.');
            }

            DB::table('patient_whatsapp_messages')->where('id', $messageId)->update([
                'status' => 'sent',
                'sent_at' => $now,
                'provider_message_id' => $body['messages'][0]['id'] ?? $message->provider_message_id,
                'provider_payload' => json_encode($body),
                'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            DB::table('patient_whatsapp_messages')->where('id', $messageId)->update([
                'status' => 'failed',
                'failed_at' => $now,
                'error_code' => 'send_failed',
                'error_message' => $exception->getMessage(),
                'updated_at' => $now,
            ]);
        }
    }

    private function cloudPayload(object $message): array
    {
        if ($message->message_type === 'template' && $message->whatsapp_template_id) {
            $template = DB::table('whatsapp_templates')->where('id', $message->whatsapp_template_id)->first();
            if ($template?->provider_template_name) {
                return [
                    'messaging_product' => 'whatsapp',
                    'to' => $message->whatsapp_number,
                    'type' => 'template',
                    'template' => [
                        'name' => $template->provider_template_name,
                        'language' => ['code' => $template->language_code ?: 'en_US'],
                    ],
                ];
            }
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->whatsapp_number,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message->message,
            ],
        ];
    }

    private function processStatusWebhook(array $status, array $value): bool
    {
        $providerId = $status['id'] ?? null;
        $eventStatus = $status['status'] ?? 'sent';
        $eventUid = 'status:'.$providerId.':'.$eventStatus.':'.($status['timestamp'] ?? now()->timestamp);
        if (! $this->recordWebhookEvent($eventUid, 'status', $value, $providerId, $status['recipient_id'] ?? null)) {
            return false;
        }

        $updates = [
            'status' => $eventStatus,
            'updated_at' => now(),
        ];
        if ($eventStatus === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($eventStatus === 'read') {
            $updates['read_at'] = now();
        } elseif ($eventStatus === 'failed') {
            $updates['failed_at'] = now();
            $updates['error_code'] = $status['errors'][0]['code'] ?? 'webhook_failed';
            $updates['error_message'] = $status['errors'][0]['title'] ?? 'Delivery failed.';
        }

        DB::table('patient_whatsapp_messages')->where('provider_message_id', $providerId)->update($updates);
        DB::table('whatsapp_webhook_events')->where('event_uid', $eventUid)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);

        return true;
    }

    private function processIncomingMessageWebhook(array $message, array $value): bool
    {
        $providerId = $message['id'] ?? null;
        $number = $message['from'] ?? null;
        $eventUid = 'message:'.$providerId;
        if (! $providerId || ! $number || ! $this->recordWebhookEvent($eventUid, 'incoming_message', $value, $providerId, $number)) {
            return false;
        }

        $now = now();
        $patient = $this->resolvePatientForNumber($number, $now);
        $conversation = $this->resolveConversation([
            'patient_id' => $patient->id,
            'whatsapp_number' => $number,
        ], $now);

        $type = $message['type'] ?? 'text';
        $body = match ($type) {
            'text' => $message['text']['body'] ?? '',
            'image' => $message['image']['caption'] ?? '[Image received]',
            'document' => $message['document']['caption'] ?? ($message['document']['filename'] ?? '[Document received]'),
            default => '['.str($type)->replace('_', ' ')->title().' received]',
        };

        $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
            'patient_id' => $patient->id,
            'whatsapp_conversation_id' => $conversation->id,
            'branch_id' => $conversation->branch_id,
            'assigned_to' => $conversation->assigned_to,
            'direction' => 'in',
            'whatsapp_number' => $number,
            'message' => $body,
            'message_type' => $type,
            'status' => 'replied',
            'reply_at' => $now,
            'provider_message_id' => $providerId,
            'provider_event_id' => $eventUid,
            'provider_payload' => json_encode($message),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (isset($message['image']) || isset($message['document'])) {
            $media = $message['image'] ?? $message['document'];
            DB::table('whatsapp_message_attachments')->insert([
                'patient_whatsapp_message_id' => $messageId,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'in',
                'media_id' => $media['id'] ?? null,
                'original_filename' => $media['filename'] ?? null,
                'mime_type' => $media['mime_type'] ?? null,
                'sha256' => $media['sha256'] ?? null,
                'status' => 'received',
                'metadata' => json_encode($media),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (in_array(Str::lower(trim($body)), ['stop', 'unsubscribe', 'opt out', 'opt-out'], true)) {
            $this->recordOptOut((int) $patient->id, $number, $now, 'incoming_reply');
        }

        $this->touchConversation((int) $conversation->id, $body, $now, true, 'replied');
        $this->timeline((int) $patient->id, $conversation->branch_id, null, 'whatsapp', 'WhatsApp reply received', $now, $body, 'patient_whatsapp_message', $messageId, ['conversation_id' => $conversation->id]);
        DB::table('whatsapp_webhook_events')->where('event_uid', $eventUid)->update(['status' => 'processed', 'processed_at' => $now, 'updated_at' => $now]);

        return true;
    }

    private function recordWebhookEvent(string $eventUid, string $eventType, array $payload, ?string $providerMessageId, ?string $number): bool
    {
        if (DB::table('whatsapp_webhook_events')->where('event_uid', $eventUid)->exists()) {
            return false;
        }

        DB::table('whatsapp_webhook_events')->insert([
            'event_uid' => $eventUid,
            'provider' => 'meta_cloud',
            'event_type' => $eventType,
            'phone_number_id' => $payload['metadata']['phone_number_id'] ?? null,
            'whatsapp_number' => $number,
            'provider_message_id' => $providerMessageId,
            'payload' => json_encode($payload),
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function sendAutomationMessage(object $rule, array $context): int
    {
        return DB::transaction(function () use ($rule, $context): int {
            $now = now();
            $patient = DB::table('patients')->where('id', $context['patient_id'])->first();
            if (! $patient || ! $patient->whatsapp_number) {
                throw ValidationException::withMessages(['patient_id' => 'Patient or WhatsApp number was not found.']);
            }
            if ((bool) $rule->requires_opt_in && ! (bool) ($patient->whatsapp_opt_in ?? false)) {
                throw ValidationException::withMessages(['patient_id' => 'Automatic message skipped because patient has not opted in.']);
            }
            $template = $rule->whatsapp_template_id ? DB::table('whatsapp_templates')->where('id', $rule->whatsapp_template_id)->first() : null;
            $conversation = $this->resolveConversation([
                'patient_id' => $patient->id,
                'branch_id' => $context['branch_id'] ?? $rule->branch_id,
                'whatsapp_number' => $patient->whatsapp_number,
            ], $now);

            $body = $template ? $this->renderTemplate($template, $patient, $context) : 'WhatsApp automated update from ClearView Optical.';
            $body = $this->applyContentPolicy($body, $template, $patient);

            $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
                'patient_id' => $patient->id,
                'whatsapp_conversation_id' => $conversation->id,
                'branch_id' => $context['branch_id'] ?? $conversation->branch_id,
                'whatsapp_template_id' => $template?->id,
                'whatsapp_automation_rule_id' => $rule->id,
                'direction' => 'out',
                'whatsapp_number' => $patient->whatsapp_number,
                'message' => $body,
                'message_type' => $template ? 'template' : 'text',
                'context_type' => $context['context_type'] ?? null,
                'context_id' => $context['context_id'] ?? null,
                'status' => 'queued',
                'queued_at' => $now,
                'metadata' => json_encode(['send_mode' => 'automation', 'trigger_key' => $rule->trigger_key]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('whatsapp_automation_rules')->where('id', $rule->id)->update(['last_run_at' => $now, 'updated_at' => $now]);
            $this->dispatchMessage($messageId);
            $fresh = DB::table('patient_whatsapp_messages')->where('id', $messageId)->first();
            $this->touchConversation((int) $conversation->id, $body, $fresh->sent_at ?: $now, false, $fresh->status);
            $this->timeline((int) $patient->id, $conversation->branch_id, null, 'whatsapp', 'WhatsApp automation sent', $fresh->sent_at ?: $now, $body, 'patient_whatsapp_message', $messageId, ['automation_rule_id' => $rule->id, 'trigger' => $rule->trigger_key]);

            return $messageId;
        });
    }

    private function touchConversation(int $conversationId, string $preview, $at, bool $incoming, string $messageStatus): void
    {
        $conversation = DB::table('whatsapp_conversations')->where('id', $conversationId)->first();
        if (! $conversation) {
            return;
        }

        DB::table('whatsapp_conversations')->where('id', $conversationId)->update([
            'status' => $messageStatus === 'failed' ? 'pending' : ($conversation->status === 'resolved' && $incoming ? 'open' : $conversation->status),
            'unread_count' => $incoming ? (int) $conversation->unread_count + 1 : $conversation->unread_count,
            'last_message_preview' => Str::limit($preview, 140),
            'last_message_at' => $at,
            'updated_at' => now(),
        ]);
    }

    private function renderTemplate(object $template, object $patient, array $context): string
    {
        $replacements = [
            'patient_name' => $patient->full_name,
            'patient_code' => $patient->patient_code,
            'whatsapp_number' => $patient->whatsapp_number,
            'appointment_date' => $this->contextValue($context, 'patient_appointment', 'appointment_at', fn ($value) => Carbon::parse($value)->format('Y-m-d H:i')),
            'order_number' => $this->contextValue($context, 'optical_order', 'order_number'),
            'invoice_number' => $this->contextValue($context, 'sales_invoice', 'invoice_number'),
            'balance_due' => $this->contextValue($context, 'sales_invoice', 'balance_due'),
            'branch_name' => $this->contextValue($context, 'branch', 'name'),
        ];

        $body = $template->body;
        foreach ($replacements as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $body);
        }

        return $body;
    }

    private function contextValue(array $context, string $type, string $column, ?callable $formatter = null)
    {
        if (($context['context_type'] ?? null) !== $type || empty($context['context_id'])) {
            return null;
        }

        $table = match ($type) {
            'patient_appointment' => 'patient_appointments',
            'optical_order' => 'optical_orders',
            'sales_invoice' => 'sales_invoices',
            'branch' => 'branches',
            default => null,
        };
        if (! $table || ! Schema::hasTable($table)) {
            return null;
        }

        $value = DB::table($table)->where('id', $context['context_id'])->value($column);

        return $formatter && $value ? $formatter($value) : $value;
    }

    private function applyContentPolicy(string $body, ?object $template, object $patient): string
    {
        $settings = $this->settings();
        $policyKey = $patient->whatsapp_content_policy ?? $settings->content_policy ?? config('whatsapp.content_policy.default');
        $policy = config('whatsapp.content_policy.policies')[$policyKey] ?? config('whatsapp.content_policy.policies.standard');

        if (($template?->send_sensitive_details ?? false) && ! ($policy['allow_sensitive_clinical_details'] ?? false)) {
            return 'ClearView Optical has an update for you. Please contact the store or visit us for details.';
        }

        return $body;
    }

    private function extractVariables(string $body): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function recordOptOut(int $patientId, string $number, $now, string $source): void
    {
        DB::table('patients')->where('id', $patientId)->update([
            'whatsapp_opt_in' => false,
            'whatsapp_opt_out' => true,
            'whatsapp_consent_at' => $now,
            'whatsapp_consent_source' => $source,
            'updated_at' => $now,
        ]);

        DB::table('whatsapp_consent_events')->insert([
            'patient_id' => $patientId,
            'whatsapp_number' => $number,
            'consent_status' => 'opt_out',
            'source' => $source,
            'policy_version' => 'standard',
            'recorded_at' => $now,
            'notes' => 'Patient opted out by WhatsApp reply.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function responseRate(string $dateFrom, string $dateTo): float
    {
        $outbound = DB::table('patient_whatsapp_messages')
            ->where('direction', 'out')
            ->whereBetween(DB::raw('date(coalesce(sent_at, created_at))'), [$dateFrom, $dateTo])
            ->count();
        if ($outbound === 0) {
            return 0;
        }

        $inbound = DB::table('patient_whatsapp_messages')
            ->where('direction', 'in')
            ->whereBetween(DB::raw('date(created_at)'), [$dateFrom, $dateTo])
            ->count();

        return round(($inbound / $outbound) * 100, 1);
    }

    private function reportPreview(?int $branchId): array
    {
        return [
            'messages_by_status' => DB::table('patient_whatsapp_messages')
                ->selectRaw('status, count(*) as messages')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'conversations_by_status' => DB::table('whatsapp_conversations')
                ->selectRaw('status, count(*) as conversations')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ];
    }

    private function settings(): ?object
    {
        return DB::table('whatsapp_settings')->orderByDesc('id')->first();
    }

    private function publicSettings(): array
    {
        $settings = $this->settings();

        return [
            'provider' => 'meta_cloud',
            'mode' => $settings->mode ?? config('whatsapp.provider.mode'),
            'api_version' => $settings->api_version ?? config('whatsapp.provider.api_version'),
            'phone_number_id_configured' => (bool) ($settings->phone_number_id ?? config('whatsapp.provider.phone_number_id')),
            'business_account_id_configured' => (bool) ($settings->business_account_id ?? config('whatsapp.provider.business_account_id')),
            'access_token_configured' => (bool) ($settings->access_token_ciphertext ?? config('whatsapp.provider.access_token')),
            'app_secret_configured' => (bool) ($settings->app_secret ?? config('whatsapp.provider.app_secret')),
            'webhook_verify_token_configured' => (bool) ($settings->webhook_verify_token ?? config('whatsapp.provider.webhook_verify_token')),
            'webhook_url' => url('/webhooks/whatsapp'),
            'auto_send_enabled' => (bool) ($settings->auto_send_enabled ?? true),
            'media_allowed' => (bool) ($settings->media_allowed ?? true),
            'content_policy' => $settings->content_policy ?? config('whatsapp.content_policy.default'),
            'retry_limit' => (int) ($settings->retry_limit ?? 3),
        ];
    }

    private function accessToken(?object $settings): ?string
    {
        if ($settings?->access_token_ciphertext) {
            return decrypt($settings->access_token_ciphertext);
        }

        return config('whatsapp.provider.access_token');
    }

    private function appSecret(?object $settings): ?string
    {
        if ($settings?->app_secret) {
            return decrypt($settings->app_secret);
        }

        return config('whatsapp.provider.app_secret');
    }

    private function timeline(int $patientId, ?int $branchId, ?int $userId, string $type, string $title, $eventAt, ?string $description, string $sourceType, int $sourceId, ?array $metadata = null): void
    {
        DB::table('patient_timeline_events')->insert([
            'patient_id' => $patientId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'event_type' => $type,
            'event_title' => $title,
            'event_at' => $eventAt,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function audit(string $action, string $type, int $id, ?int $branchId, ?array $before, ?array $after, $now): void
    {
        DB::table('audit_logs')->insert([
            'branch_id' => $branchId,
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'before_values' => $before ? json_encode($before) : null,
            'after_values' => $after ? json_encode($after) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function companyId(): int
    {
        return (int) DB::table('companies')->orderBy('id')->value('id');
    }

    private function nextNumber(string $prefix, string $table, string $column): string
    {
        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (DB::table($table)->where($column, $number)->exists());

        return $number;
    }

    private function respond(Request $request, array $payload, string $page, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload);
        }

        $params = ['page' => $page];
        if ($payload['conversation_id'] ?? null) {
            $params['conversation_id'] = $payload['conversation_id'];
        } elseif ($request->integer('conversation_id')) {
            $params['conversation_id'] = $request->integer('conversation_id');
        }

        return redirect()->route('whatsapp.app', $params)->with('status', __($message));
    }
}
