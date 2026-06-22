@extends('layouts.erp', ['activeNav' => 'whatsapp'])

@section('title', __('nav.whatsapp'))

@php
    $selected = $selectedConversation ?? [];
    $conversation = $selected['conversation'] ?? null;
    $cards = [
        ['icon' => 'whatsapp', 'key' => 'inbox', 'page' => 'inbox', 'count' => $metrics['unread_messages'] ?? 0],
        ['icon' => 'configuration', 'key' => 'templates', 'page' => 'templates', 'count' => $templates->count() ?? 0],
        ['icon' => 'appointments', 'key' => 'automations', 'page' => 'automations', 'count' => $automationRules->count() ?? 0],
        ['icon' => 'patients', 'key' => 'consent', 'page' => 'consent', 'count' => $metrics['opt_outs'] ?? 0],
        ['icon' => 'reports', 'key' => 'logs', 'page' => 'logs', 'count' => $metrics['sent_month'] ?? 0],
        ['icon' => 'configuration', 'key' => 'settings', 'page' => 'settings', 'count' => null],
    ];
@endphp

@section('content')
    @if (! $databaseReady)
        <x-empty-state :title="__('setup.database_not_ready')" :message="__('setup.run_migrations')" />
    @else
        <section class="page-header"><div><p class="eyebrow">{{ __('nav.whatsapp') }}</p><h1>{{ __('whatsapp.page.'.$page) }}</h1><p>{{ __('whatsapp.subtitle') }}</p></div><a class="button" href="{{ route('whatsapp.app', ['page' => 'inbox', 'lang' => app()->getLocale()]) }}"><x-icon name="whatsapp" />{{ __('whatsapp.open_inbox') }}</a></section>

        @if($page === 'inbox')
            <section class="metric-grid"><x-metric-card :label="__('whatsapp.open_conversations')" :value="$metrics['open_conversations']" /><x-metric-card :label="__('whatsapp.unread')" :value="$metrics['unread_messages']" /><x-metric-card :label="__('whatsapp.sent_month')" :value="$metrics['sent_month']" /><x-metric-card :label="__('whatsapp.failed_month')" :value="$metrics['failed_month']" /></section>
            <section class="section"><div class="section-header"><div><h2>{{ __('whatsapp.workspace') }}</h2><p>{{ __('whatsapp.workspace_hint') }}</p></div></div><div class="module-grid">@foreach($cards as $card)<x-module-card :icon="$card['icon']" :title="__('whatsapp.card.'.$card['key'].'.title')" :description="__('whatsapp.card.'.$card['key'].'.description')" :action="__('common.open')" :href="route('whatsapp.app', ['page' => $card['page'], 'lang' => app()->getLocale()])" :count="$card['count']" />@endforeach</div></section>
            <form class="filterbar section" method="GET"><input type="hidden" name="lang" value="{{ app()->getLocale() }}"><input name="q" value="{{ $query }}" type="search" placeholder="{{ __('whatsapp.search_placeholder') }}"><select name="status"><option value="">{{ __('whatsapp.all_statuses') }}</option>@foreach($conversationStatuses as $key => $label)<option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>@endforeach</select><button class="button button-secondary" type="submit">{{ __('common.search') }}</button></form>
            <section class="table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('whatsapp.contact') }}</th><th>{{ __('whatsapp.last_message') }}</th><th>{{ __('whatsapp.assigned_to') }}</th><th>{{ __('whatsapp.unread') }}</th><th>{{ __('common.status') }}</th><th></th></tr></thead><tbody>@forelse($conversations as $row)<tr><td><strong>{{ $row->patient_name ?: $row->contact_name ?: $row->whatsapp_number }}</strong><br><span class="table-meta">{{ $row->whatsapp_number }}</span></td><td>{{ $row->last_message_preview }}<br><span class="table-meta">{{ $row->last_message_at }}</span></td><td>{{ $row->assigned_to_name ?: __('common.not_set') }}</td><td>{{ $row->unread_count }}</td><td><x-status-badge :status="$row->status" /></td><td><a class="button button-ghost" href="{{ route('whatsapp.app', ['page' => 'conversation', 'conversation_id' => $row->id]) }}">{{ __('common.open') }}</a></td></tr>@empty<tr><td colspan="6"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if($page === 'conversation')
            @if($conversation)
                <section class="panel"><div class="section-header"><div><h2>{{ $conversation->patient_name ?: $conversation->contact_name ?: $conversation->whatsapp_number }}</h2><p>{{ $conversation->whatsapp_number }}</p></div><x-status-badge :status="$conversation->status" /></div><div class="cards-grid">@forelse(($selected['messages'] ?? collect()) as $message)<article class="panel"><x-status-badge :status="$message->status" /><p>{{ $message->message }}</p><span class="table-meta">{{ $message->created_at }}</span></article>@empty<x-empty-state />@endforelse</div><form class="form-grid section" method="POST" action="{{ route('whatsapp.messages.send') }}">@csrf<input type="hidden" name="conversation_id" value="{{ $conversation->id }}"><input type="hidden" name="branch_id" value="{{ $conversation->branch_id }}"><div class="field"><label>{{ __('whatsapp.template') }}</label><select name="template_id"><option value="">{{ __('whatsapp.write_message') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></div><div class="field field-wide"><label>{{ __('whatsapp.message') }}</label><textarea name="message" placeholder="{{ __('whatsapp.message_placeholder') }}"></textarea></div><div class="form-actions"><button class="button" type="submit">{{ __('whatsapp.send') }}</button></div></form></section>
            @else<x-empty-state />@endif
        @endif

        @if($page === 'templates')
            <section class="panel"><div class="section-header"><div><h2>{{ __('whatsapp.new_template') }}</h2><p>{{ __('whatsapp.template_hint') }}</p></div></div><form class="form-grid" method="POST" action="{{ route('whatsapp.templates.save') }}">@csrf<div class="field"><label class="required">{{ __('common.name') }}</label><input name="name" required></div><div class="field"><label>{{ __('common.category') }}</label><select name="category">@foreach($templateCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>{{ __('whatsapp.trigger') }}</label><select name="trigger_key"><option value="">{{ __('whatsapp.manual_only') }}</option>@foreach($templateKeys as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>{{ __('whatsapp.language') }}</label><input name="language_code" value="{{ app()->getLocale() === 'ar' ? 'ar' : 'en_US' }}"></div><div class="field field-wide"><label class="required">{{ __('whatsapp.message') }}</label><textarea name="body" required placeholder="{{ __('whatsapp.template_placeholder') }}"></textarea></div><input type="hidden" name="is_active" value="1"><div class="form-actions"><button class="button" type="submit">{{ __('whatsapp.save_template') }}</button></div></form></section>
            <section class="section table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.name') }}</th><th>{{ __('common.category') }}</th><th>{{ __('whatsapp.trigger') }}</th><th>{{ __('whatsapp.language') }}</th><th>{{ __('common.status') }}</th></tr></thead><tbody>@forelse($templates as $template)<tr><td>{{ $template->name }}<br><span class="table-meta">{{ $template->body }}</span></td><td>{{ $templateCategories[$template->category] ?? $template->category }}</td><td>{{ $templateKeys[$template->trigger_key] ?? __('whatsapp.manual_only') }}</td><td>{{ $template->language_code }}</td><td><x-status-badge :status="$template->is_active ? 'active' : 'inactive'" /></td></tr>@empty<tr><td colspan="5"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if($page === 'consent')
            <section class="panel"><div class="section-header"><div><h2>{{ __('whatsapp.record_consent') }}</h2><p>{{ __('whatsapp.consent_hint') }}</p></div></div><form class="form-grid" method="POST" action="{{ route('whatsapp.consent.update') }}">@csrf<div class="field"><label>{{ __('patients.patient') }}</label><select name="patient_id"><option value="">{{ __('common.not_set') }}</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_code }} - {{ $patient->full_name }}</option>@endforeach</select></div><div class="field"><label>{{ __('patients.whatsapp') }}</label><input name="whatsapp_number" type="tel"></div><div class="field"><label>{{ __('common.status') }}</label><select name="consent_status"><option value="opt_in">{{ __('whatsapp.opt_in') }}</option><option value="opt_out">{{ __('whatsapp.opt_out') }}</option></select></div><input type="hidden" name="source" value="manual"><div class="form-actions"><button class="button" type="submit">{{ __('common.save') }}</button></div></form></section>
        @endif

        @if($page === 'settings')
            <section class="metric-grid">
                <x-metric-card :label="__('whatsapp.connection_mode')" :value="strtoupper($publicSettings['mode'])" :hint="$publicSettings['mode'] === 'live' ? __('whatsapp.live_mode_hint') : __('whatsapp.sandbox_mode_hint')" />
                <x-metric-card :label="__('whatsapp.phone_number_id')" :value="$publicSettings['phone_number_id_configured'] ? __('common.configured') : __('common.not_set')" />
                <x-metric-card :label="__('whatsapp.access_token')" :value="$publicSettings['access_token_configured'] ? __('common.configured') : __('common.not_set')" />
                <x-metric-card :label="__('whatsapp.webhook_security')" :value="$publicSettings['app_secret_configured'] ? __('common.configured') : __('common.not_set')" />
            </section>

            <section class="panel section">
                <div class="section-header"><div><h2>{{ __('whatsapp.meta_connection') }}</h2><p>{{ __('whatsapp.meta_connection_hint') }}</p></div></div>
                <form class="form-grid" method="POST" action="{{ route('whatsapp.settings.save') }}">
                    @csrf
                    <div class="field"><label class="required">{{ __('whatsapp.connection_mode') }}</label><select name="mode" required><option value="sandbox" @selected(old('mode', $settings->mode ?? config('whatsapp.provider.mode')) === 'sandbox')>{{ __('whatsapp.sandbox') }}</option><option value="live" @selected(old('mode', $settings->mode ?? config('whatsapp.provider.mode')) === 'live')>{{ __('whatsapp.live') }}</option></select></div>
                    <div class="field"><label class="required">{{ __('whatsapp.api_version') }}</label><input name="api_version" required value="{{ old('api_version', $settings->api_version ?? config('whatsapp.provider.api_version')) }}" placeholder="v23.0"></div>
                    <div class="field"><label>{{ __('whatsapp.phone_number_id') }}</label><input name="phone_number_id" value="{{ old('phone_number_id', $settings->phone_number_id ?? '') }}" inputmode="numeric" autocomplete="off"></div>
                    <div class="field"><label>{{ __('whatsapp.business_account_id') }}</label><input name="business_account_id" value="{{ old('business_account_id', $settings->business_account_id ?? '') }}" inputmode="numeric" autocomplete="off"></div>
                    <div class="field"><label>{{ __('whatsapp.access_token') }}</label><input name="access_token" type="password" autocomplete="new-password" placeholder="{{ $publicSettings['access_token_configured'] ? __('whatsapp.secret_saved_placeholder') : '' }}"></div>
                    <div class="field"><label>{{ __('whatsapp.app_secret') }}</label><input name="app_secret" type="password" autocomplete="new-password" placeholder="{{ $publicSettings['app_secret_configured'] ? __('whatsapp.secret_saved_placeholder') : '' }}"></div>
                    <div class="field"><label>{{ __('whatsapp.webhook_verify_token') }}</label><input name="webhook_verify_token" value="{{ old('webhook_verify_token', $settings->webhook_verify_token ?? config('whatsapp.provider.webhook_verify_token')) }}" autocomplete="off"></div>
                    <div class="field"><label>{{ __('whatsapp.content_policy') }}</label><select name="content_policy">@foreach($contentPolicies as $key => $policy)<option value="{{ $key }}" @selected(old('content_policy', $settings->content_policy ?? config('whatsapp.content_policy.default')) === $key)>{{ $policy['name'] }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('whatsapp.retry_limit') }}</label><input name="retry_limit" type="number" min="0" max="10" required value="{{ old('retry_limit', $settings->retry_limit ?? 3) }}"></div>
                    <div class="field"><label><input name="auto_send_enabled" type="checkbox" value="1" @checked(old('auto_send_enabled', $settings->auto_send_enabled ?? true))> {{ __('whatsapp.auto_send_enabled') }}</label><label><input name="media_allowed" type="checkbox" value="1" @checked(old('media_allowed', $settings->media_allowed ?? true))> {{ __('whatsapp.media_allowed') }}</label></div>
                    <div class="form-actions field-wide"><button class="button" type="submit">{{ __('whatsapp.save_connection') }}</button></div>
                </form>
            </section>

            <section class="panel section">
                <div class="section-header"><div><h2>{{ __('whatsapp.webhook_setup') }}</h2><p>{{ __('whatsapp.webhook_setup_hint') }}</p></div></div>
                <div class="form-grid">
                    <div class="field field-wide"><label>{{ __('whatsapp.callback_url') }}</label><input readonly value="{{ $publicSettings['webhook_url'] }}"></div>
                    <div class="field"><label>{{ __('whatsapp.verify_token') }}</label><input readonly value="{{ $settings->webhook_verify_token ?? config('whatsapp.provider.webhook_verify_token') }}"></div>
                    <div class="field"><label>{{ __('whatsapp.webhook_field') }}</label><input readonly value="messages"></div>
                </div>
                @if(str_starts_with($publicSettings['webhook_url'], 'http://localhost') || str_starts_with($publicSettings['webhook_url'], 'http://127.0.0.1'))
                    <div class="notice notice-error">{{ __('whatsapp.public_https_required') }}</div>
                @endif
                <form method="POST" action="{{ route('whatsapp.settings.test') }}">@csrf<button class="button button-secondary" type="submit">{{ __('whatsapp.test_connection') }}</button></form>
            </section>

            <section class="panel">
                <div class="section-header"><div><h2>{{ __('whatsapp.meta_steps') }}</h2><p>{{ __('whatsapp.meta_steps_hint') }}</p></div></div>
                <ol>
                    <li>{{ __('whatsapp.meta_step_1') }}</li>
                    <li>{{ __('whatsapp.meta_step_2') }}</li>
                    <li>{{ __('whatsapp.meta_step_3') }}</li>
                    <li>{{ __('whatsapp.meta_step_4') }}</li>
                </ol>
            </section>
        @endif

        @if(in_array($page, ['logs','failed'], true))
            @php($logRows = $page === 'failed' ? $failedMessages : $messages)
            <section class="table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.status') }}</th><th>{{ __('whatsapp.contact') }}</th><th>{{ __('whatsapp.message') }}</th><th>{{ __('common.date') }}</th></tr></thead><tbody>@forelse($logRows as $message)<tr><td><x-status-badge :status="$message->status" /></td><td>{{ $message->patient_name ?: $message->whatsapp_number }}</td><td>{{ $message->message }}</td><td>{{ $message->created_at }}</td></tr>@empty<tr><td colspan="4"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if(in_array($page, ['automations','reports','patient-panel'], true))
            <x-empty-state :title="__('whatsapp.page.'.$page)" :message="__('whatsapp.configuration_hint')" />
        @endif
    @endif
@endsection
