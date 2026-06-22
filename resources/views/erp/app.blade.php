@extends('layouts.erp', ['activeNav' => $page])

@section('title', __('nav.'.str_replace('-', '_', $page)))

@php
    $currency = config('erp.module.currency', 'SAR');
    $moduleActions = [
        'patients' => [
            ['icon' => 'patients', 'title' => 'module.patients.register.title', 'description' => 'module.patients.register.description', 'action' => 'quick.new_patient', 'href' => route('patients.app', ['page' => 'patient-create', 'lang' => app()->getLocale()])],
            ['icon' => 'search', 'title' => 'module.patients.files.title', 'description' => 'module.patients.files.description', 'action' => 'common.open', 'href' => route('patients.app', ['page' => 'patients', 'lang' => app()->getLocale()])],
            ['icon' => 'appointments', 'title' => 'module.patients.exam.title', 'description' => 'module.patients.exam.description', 'action' => 'common.open', 'href' => route('patients.app', ['page' => 'eye-exam', 'lang' => app()->getLocale()])],
            ['icon' => 'reports', 'title' => 'module.patients.prescription.title', 'description' => 'module.patients.prescription.description', 'action' => 'common.open', 'href' => route('patients.app', ['page' => 'prescription', 'lang' => app()->getLocale()])],
        ],
        'appointments' => [
            ['icon' => 'appointments', 'title' => 'module.appointments.book.title', 'description' => 'module.appointments.book.description', 'action' => 'quick.book_appointment', 'href' => route('appointments.app', ['page' => 'create', 'lang' => app()->getLocale()])],
            ['icon' => 'dashboard', 'title' => 'module.appointments.calendar.title', 'description' => 'module.appointments.calendar.description', 'action' => 'common.open', 'href' => route('appointments.app', ['page' => 'calendar', 'lang' => app()->getLocale()])],
            ['icon' => 'patients', 'title' => 'module.appointments.waiting.title', 'description' => 'module.appointments.waiting.description', 'action' => 'common.open', 'href' => route('appointments.app', ['page' => 'waiting-list', 'lang' => app()->getLocale()])],
        ],
        'optical-orders' => [
            ['icon' => 'orders', 'title' => 'module.orders.create.title', 'description' => 'module.orders.create.description', 'action' => 'quick.create_order', 'href' => route('optical-orders.app', ['page' => 'create', 'lang' => app()->getLocale()])],
            ['icon' => 'inventory', 'title' => 'module.orders.lab.title', 'description' => 'module.orders.lab.description', 'action' => 'common.open', 'href' => route('optical-orders.app', ['page' => 'lab-board', 'lang' => app()->getLocale()])],
            ['icon' => 'sales', 'title' => 'module.orders.pickup.title', 'description' => 'module.orders.pickup.description', 'action' => 'common.open', 'href' => route('optical-orders.app', ['page' => 'ready-pickup', 'lang' => app()->getLocale()])],
        ],
        'sales-pos' => [
            ['icon' => 'sales', 'title' => 'module.sales.pos.title', 'description' => 'module.sales.pos.description', 'action' => 'quick.open_pos', 'href' => route('sales.app', ['page' => 'pos', 'lang' => app()->getLocale()])],
            ['icon' => 'reports', 'title' => 'module.sales.invoices.title', 'description' => 'module.sales.invoices.description', 'action' => 'common.open', 'href' => route('sales.app', ['page' => 'invoices', 'lang' => app()->getLocale()])],
            ['icon' => 'accounting', 'title' => 'module.sales.closing.title', 'description' => 'module.sales.closing.description', 'action' => 'common.open', 'href' => route('sales.app', ['page' => 'cashier-closing', 'lang' => app()->getLocale()])],
        ],
        'inventory' => [
            ['icon' => 'inventory', 'title' => 'module.inventory.wizard.title', 'description' => 'module.inventory.wizard.description', 'action' => 'common.open', 'href' => route('inventory.app', ['page' => 'dashboard', 'lang' => app()->getLocale()])],
            ['icon' => 'search', 'title' => 'module.inventory.products.title', 'description' => 'module.inventory.products.description', 'action' => 'common.open', 'href' => route('inventory.app', ['page' => 'products', 'lang' => app()->getLocale()])],
            ['icon' => 'reports', 'title' => 'module.inventory.low_stock.title', 'description' => 'module.inventory.low_stock.description', 'action' => 'common.open', 'href' => route('inventory.app', ['page' => 'low-stock-alerts', 'lang' => app()->getLocale()])],
        ],
        'whatsapp' => [
            ['icon' => 'whatsapp', 'title' => 'module.whatsapp.inbox.title', 'description' => 'module.whatsapp.inbox.description', 'action' => 'quick.whatsapp_inbox', 'href' => route('whatsapp.app', ['page' => 'inbox', 'lang' => app()->getLocale()])],
            ['icon' => 'configuration', 'title' => 'module.whatsapp.templates.title', 'description' => 'module.whatsapp.templates.description', 'action' => 'common.configure', 'href' => route('whatsapp.app', ['page' => 'templates', 'lang' => app()->getLocale()])],
            ['icon' => 'patients', 'title' => 'module.whatsapp.consent.title', 'description' => 'module.whatsapp.consent.description', 'action' => 'common.open', 'href' => route('whatsapp.app', ['page' => 'consent', 'lang' => app()->getLocale()])],
        ],
    ];
@endphp

@section('content')
    @if (! $databaseReady)
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('app.name') }}</p>
                <h1>{{ __('setup.title') }}</h1>
                <p>{{ __('setup.description') }}</p>
            </div>
        </section>
        <x-empty-state :title="__('setup.database_not_ready')" :message="__('setup.run_migrations')" />
    @elseif ($page === 'dashboard')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('app.name') }}</p>
                <h1>{{ __('dashboard.title') }}</h1>
                <p>{{ __('dashboard.subtitle') }}</p>
            </div>
            <a class="button" href="{{ route('sales.app', ['page' => 'pos', 'lang' => app()->getLocale()]) }}">
                <x-icon name="sales" />
                <span>{{ __('quick.open_pos') }}</span>
            </a>
        </section>

        <section class="metric-grid">
            <x-metric-card :label="__('dashboard.today_sales')" :value="$currency.' '.number_format($metrics['today_sales'] ?? 0, 2)" :hint="__('dashboard.today_sales_hint')" />
            <x-metric-card :label="__('dashboard.today_appointments')" :value="$metrics['today_appointments'] ?? 0" :hint="__('dashboard.today_appointments_hint')" />
            <x-metric-card :label="__('dashboard.pending_orders')" :value="$metrics['pending_optical_orders'] ?? 0" :hint="__('dashboard.pending_orders_hint')" />
            <x-metric-card :label="__('dashboard.ready_pickup')" :value="$metrics['ready_for_pickup'] ?? 0" :hint="__('dashboard.ready_pickup_hint')" />
            <x-metric-card :label="__('dashboard.low_stock')" :value="$metrics['low_stock_products'] ?? 0" :hint="__('dashboard.low_stock_hint')" />
            <x-metric-card :label="__('dashboard.unread_whatsapp')" :value="$metrics['whatsapp_unread'] ?? 0" :hint="__('dashboard.unread_whatsapp_hint')" />
        </section>

        <section class="section">
            <div class="section-header">
                <div>
                    <h2>{{ __('section.workspace') }}</h2>
                    <p>{{ __('section.workspace_hint') }}</p>
                </div>
            </div>
            <div class="module-grid">
                @foreach (config('erp.navigation') as $item)
                    @continue(($item['page'] ?? '') === 'dashboard')
                    <x-module-card
                        :icon="$item['icon']"
                        :title="__($item['label_key'])"
                        :description="__('nav_description.'.$item['page'])"
                        :action="__('common.open')"
                        :href="route($item['route'], ['page' => $item['page'], 'lang' => app()->getLocale()])"
                    />
                @endforeach
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <div>
                    <h2>{{ __('section.worklists') }}</h2>
                    <p>{{ __('section.worklists_hint') }}</p>
                </div>
            </div>
            <div class="cards-grid">
                @foreach ($worklists as $key => $rows)
                    <article class="panel">
                        <h3>{{ __('worklist.'.$key) }}</h3>
                        @forelse ($rows as $row)
                            <p><strong>{{ $row->full_name ?? $row->patient ?? $row->product ?? $row->invoice_number ?? $row->conversation_number ?? $row->order_number ?? '' }}</strong><br><span class="table-meta">{{ $row->appointment_at ?? $row->phone ?? $row->branch ?? $row->status ?? '' }}</span></p>
                        @empty
                            <x-empty-state :message="__('common.empty_worklist')" />
                        @endforelse
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <div>
                    <h2>{{ __('section.workflow') }}</h2>
                    <p>{{ __('section.workflow_hint') }}</p>
                </div>
            </div>
            <div class="cards-grid">
                @foreach ($workflows as $workflow)
                    <article class="panel">
                        <h3>{{ __($workflow['title_key']) }}</h3>
                        <x-status-badge :status="$workflow['status_key'] === 'common.ready' ? 'completed' : 'pending'" />
                        <p class="table-meta">{{ $workflow['progress'] }}%</p>
                        <div class="tabs">
                            @foreach ($workflow['steps'] as $step)
                                <span class="tab {{ $step['done'] ? 'active' : '' }}">{{ __($step['label_key']) }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @elseif (array_key_exists($page, $moduleActions))
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('section.workspace') }}</p>
                <h1>{{ __('nav.'.str_replace('-', '_', $page)) }}</h1>
                <p>{{ __('nav_description.'.$page) }}</p>
            </div>
            <a class="button" href="{{ $moduleActions[$page][0]['href'] }}">
                <x-icon :name="$moduleActions[$page][0]['icon']" />
                <span>{{ __($moduleActions[$page][0]['action']) }}</span>
            </a>
        </section>
        <div class="module-grid">
            @foreach ($moduleActions[$page] as $card)
                <x-module-card
                    :icon="$card['icon']"
                    :title="__($card['title'])"
                    :description="__($card['description'])"
                    :action="__($card['action'])"
                    :href="$card['href']"
                />
            @endforeach
        </div>
    @elseif ($page === 'search')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('common.search') }}</p>
                <h1>{{ __('search.title') }}</h1>
                <p>{{ __('search.subtitle') }}</p>
            </div>
        </section>

        @if ($query === '')
            <x-empty-state :title="__('search.empty_title')" :message="__('search.empty')" />
        @else
            <div class="cards-grid">
                @foreach ($globalResults as $group => $results)
                    <article class="panel">
                        <h2>{{ __('search_group.'.$group) }}</h2>
                        @forelse ($results as $result)
                            <p>
                                <a href="{{ $result['url'] }}"><strong>{{ $result['title'] }}</strong></a><br>
                                <span class="table-meta">{{ $result['code'] }} - {{ $result['meta'] }}</span>
                            </p>
                        @empty
                            <x-empty-state :message="__('common.no_data')" />
                        @endforelse
                    </article>
                @endforeach
            </div>
        @endif
    @elseif ($page === 'accounting')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('nav.accounting') }}</p>
                <h1>{{ __('accounting.title') }}</h1>
                <p>{{ __('accounting.subtitle') }}</p>
            </div>
        </section>
        <section class="metric-grid">
            <x-metric-card :label="__('accounting.accounts')" :value="$accounting['accounts'] ?? 0" />
            <x-metric-card :label="__('accounting.journals')" :value="$accounting['journals'] ?? 0" />
            <x-metric-card :label="__('accounting.month_debit')" :value="$currency.' '.number_format($accounting['month_debit'] ?? 0, 2)" />
            <x-metric-card :label="__('accounting.open_closings')" :value="$accounting['open_closings'] ?? 0" />
        </section>
        <section class="section">
            <div class="section-header"><div><h2>{{ __('accounting.workspace') }}</h2><p>{{ __('accounting.workspace_hint') }}</p></div></div>
            <div class="module-grid">
                <x-module-card icon="accounting" :title="__('accounting.accounts')" :description="__('accounting.accounts_hint')" :action="__('common.open')" :href="route('erp.app', ['page' => 'configuration', 'group' => 'chart_of_accounts', 'lang' => app()->getLocale()])" :count="$accounting['accounts'] ?? 0" />
                <x-module-card icon="reports" :title="__('accounting.vouchers')" :description="__('accounting.vouchers_hint')" :action="__('common.view')" :href="route('erp.reports.export', ['report' => 'accounting-journals', 'format' => 'print', 'lang' => app()->getLocale()])" />
                <x-module-card icon="reports" :title="__('accounting.invoices')" :description="__('accounting.invoices_hint')" :action="__('common.open')" :href="route('sales.app', ['page' => 'invoices', 'lang' => app()->getLocale()])" />
                <x-module-card icon="sales" :title="__('accounting.payments')" :description="__('accounting.payments_hint')" :action="__('common.open')" :href="route('sales.app', ['page' => 'payments', 'lang' => app()->getLocale()])" />
                <x-module-card icon="appointments" :title="__('accounting.daily_close')" :description="__('accounting.daily_close_hint')" :action="__('common.open')" :href="route('sales.app', ['page' => 'cashier-closing', 'lang' => app()->getLocale()])" :count="$accounting['open_closings'] ?? 0" />
                <x-module-card icon="reports" :title="__('nav.reports')" :description="__('reports.description')" :action="__('common.open')" :href="route('erp.app', ['page' => 'reports', 'lang' => app()->getLocale()])" />
            </div>
        </section>
        @if(auth()->user()->hasPermission('accounting.post'))
            <section class="section panel" id="voucher-wizard">
                <div class="section-header"><div><h2>{{ __('accounting.new_voucher') }}</h2><p>{{ __('accounting.new_voucher_hint') }}</p></div><x-status-badge status="draft" /></div>
                <form method="POST" action="{{ route('accounting.vouchers.store') }}" data-wizard data-draft-key="accounting-voucher-draft" data-accounting-lines>
                    @csrf
                    <section class="wizard">
                        <aside class="wizard-steps">
                            @foreach(['details', 'lines', 'review'] as $index => $step)
                                <button class="wizard-step-button {{ $index === 0 ? 'active' : '' }}" type="button" data-wizard-step="{{ $step }}"><span class="wizard-step-number">{{ $index + 1 }}</span><span>{{ __('accounting.step.'.$step) }}</span></button>
                            @endforeach
                        </aside>
                        <div>
                            <div class="wizard-panel active" data-wizard-panel="details">
                                <div class="wizard-progress"><span data-wizard-progress></span></div>
                                <div class="form-grid">
                                    <div class="field"><label class="required">{{ __('common.branch') }}</label><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($branchId ?? null) === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                                    <div class="field"><label class="required">{{ __('common.date') }}</label><input name="journal_date" type="date" value="{{ now()->toDateString() }}" required></div>
                                    <div class="field field-wide"><label class="required">{{ __('common.description') }}</label><textarea name="description" required></textarea></div>
                                </div>
                                <div class="wizard-actions"><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div>
                            </div>
                            <div class="wizard-panel" data-wizard-panel="lines">
                                <div class="wizard-progress"><span data-wizard-progress></span></div>
                                <div class="table-scroll"><table><thead><tr><th>{{ __('accounting.account') }}</th><th>{{ __('common.description') }}</th><th>{{ __('accounting.debit') }}</th><th>{{ __('accounting.credit') }}</th><th></th></tr></thead><tbody data-accounting-line-list>
                                    @for($index = 0; $index < 2; $index++)
                                        <tr data-accounting-line><td><select name="lines[{{ $index }}][account_id]" required><option value="">{{ __('accounting.select_account') }}</option>@foreach($accounting['accounts_list'] as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td><td><input name="lines[{{ $index }}][description]"></td><td><input name="lines[{{ $index }}][debit]" type="number" min="0" step="0.01" value="{{ $index === 0 ? '0.00' : '0.00' }}"></td><td><input name="lines[{{ $index }}][credit]" type="number" min="0" step="0.01" value="0.00"></td><td><button class="button button-ghost" type="button" data-remove-accounting-line aria-label="{{ __('common.delete') }}">&times;</button></td></tr>
                                    @endfor
                                </tbody></table></div>
                                <button class="button button-secondary" type="button" data-add-accounting-line>{{ __('accounting.add_line') }}</button>
                                <div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div>
                            </div>
                            <div class="wizard-panel" data-wizard-panel="review">
                                <div class="wizard-progress"><span data-wizard-progress></span></div>
                                <h2>{{ __('accounting.review_voucher') }}</h2><p>{{ __('accounting.balance_required') }}</p>
                                <label class="inline-choice"><input type="checkbox" name="post_now" value="1"> {{ __('accounting.post_now') }}</label>
                                <div class="notice notice-warning">{{ __('accounting.posting_warning') }}</div>
                                <div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="submit">{{ __('common.save') }}</button></div>
                            </div>
                        </div>
                    </section>
                    <template data-accounting-line-template><tr data-accounting-line><td><select data-name="account_id"><option value="">{{ __('accounting.select_account') }}</option>@foreach($accounting['accounts_list'] as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td><td><input data-name="description"></td><td><input data-name="debit" type="number" min="0" step="0.01" value="0.00"></td><td><input data-name="credit" type="number" min="0" step="0.01" value="0.00"></td><td><button class="button button-ghost" type="button" data-remove-accounting-line aria-label="{{ __('common.delete') }}">&times;</button></td></tr></template>
                </form>
            </section>
            @if(($accounting['drafts'] ?? collect())->isNotEmpty())
                <section class="section panel"><h2>{{ __('accounting.draft_vouchers') }}</h2><div class="table-scroll"><table><thead><tr><th>{{ __('common.number') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.description') }}</th><th>{{ __('common.total') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>@foreach($accounting['drafts'] as $draft)<tr><td>{{ $draft->journal_number }}</td><td>{{ $draft->journal_date }}</td><td>{{ $draft->description }}</td><td>{{ $currency }} {{ number_format($draft->total_debit, 2) }}</td><td><form method="POST" action="{{ route('accounting.vouchers.post', $draft->id) }}">@csrf<button class="button" type="submit">{{ __('accounting.post') }}</button></form></td></tr>@endforeach</tbody></table></div></section>
            @endif
        @endif
        @if(auth()->user()->hasPermission('accounting.close'))
            <section class="section module-grid">
                <form class="panel" method="POST" action="{{ route('accounting.closing.daily') }}">@csrf<h2>{{ __('accounting.daily_close') }}</h2><div class="field"><label>{{ __('common.branch') }}</label><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div><div class="field"><label>{{ __('common.date') }}</label><input name="closing_date" type="date" value="{{ now()->toDateString() }}" required></div><div class="field"><label>{{ __('accounting.actual_cash') }}</label><input name="actual_cash" type="number" min="0" step="0.01" required></div><div class="field"><label>{{ __('common.notes') }}</label><textarea name="notes"></textarea></div><button class="button" type="submit">{{ __('accounting.close_day') }}</button></form>
                <form class="panel" method="POST" action="{{ route('accounting.closing.monthly') }}">@csrf<h2>{{ __('accounting.monthly_close') }}</h2><div class="field"><label>{{ __('common.branch') }}</label><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div><div class="field"><label>{{ __('accounting.period') }}</label><input name="period" type="month" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></div><div class="field"><label>{{ __('common.notes') }}</label><textarea name="notes"></textarea></div><button class="button" type="submit">{{ __('accounting.close_month') }}</button></form>
            </section>
        @endif
        <section class="section panel">
            <h2>{{ __('accounting.recent_journals') }}</h2>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>{{ __('common.number') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.description') }}</th><th>{{ __('common.total') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                    <tbody>
                        @forelse (($accounting['recent_journals'] ?? collect()) as $journal)
                            <tr><td>{{ $journal->journal_number }}</td><td>{{ $journal->journal_date }}</td><td>{{ $journal->description }}</td><td>{{ $currency }} {{ number_format($journal->total_debit, 2) }}</td><td><x-status-badge :status="$journal->status" /><a class="button button-ghost" href="{{ route('documents.accounting-vouchers.show', ['journal' => $journal->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a><a class="button button-ghost" href="{{ route('documents.accounting-vouchers.show', ['journal' => $journal->id, 'lang' => app()->getLocale(), 'format' => 'pdf']) }}">PDF</a>@if($journal->status === 'posted' && auth()->user()->hasPermission('accounting.post'))<details><summary>{{ __('accounting.reverse') }}</summary><form method="POST" action="{{ route('accounting.vouchers.reverse', $journal->id) }}">@csrf<div class="field"><input name="journal_date" type="date" value="{{ now()->toDateString() }}" required></div><div class="field"><input name="description" placeholder="{{ __('accounting.reversal_reason') }}" required></div><button class="button button-secondary" type="submit">{{ __('accounting.confirm_reversal') }}</button></form></details>@endif</td></tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($page === 'reports')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('nav.reports') }}</p>
                <h1>{{ __('reports.title') }}</h1>
                <p>{{ __('reports.subtitle') }}</p>
            </div>
        </section>
        <div class="module-grid">
            @foreach ($reports as $key => $label)
                <x-module-card
                    icon="reports"
                    :title="__('reports.'.$key)"
                    :description="__('reports.description')"
                    :action="__('common.view')"
                    :href="route('erp.reports.export', ['report' => $key, 'lang' => app()->getLocale()])"
                />
            @endforeach
        </div>
    @elseif ($page === 'configuration')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('nav.configuration') }}</p>
                <h1>{{ __('configuration.title') }}</h1>
                <p>{{ __('configuration.subtitle') }}</p>
            </div>
        </section>
        <div class="module-grid">
            <x-module-card
                icon="configuration"
                :title="__('Template Settings')"
                :description="__('Logo, bilingual document layout, paper size, headers, footers and signatures')"
                :action="__('common.configure')"
                :href="route('configuration.templates.edit', ['lang' => app()->getLocale()])"
            />
            @foreach ($configurationGroups as $group)
                <x-module-card
                    icon="configuration"
                    :title="__($group['label_key'])"
                    :description="__('configuration.database_backed').' - '.$group['count'].' '.__('common.items')"
                    :action="__('common.view')"
                    :href="$group['url']"
                />
            @endforeach
        </div>
        @if ($selectedConfigurationGroup)
            <section class="section panel" id="configuration-data">
                <div class="section-header"><div><h2>{{ __('configuration.'.$selectedConfigurationGroup) }}</h2><p>{{ __('configuration.live_data_hint') }}</p></div><x-status-badge status="active" /></div>
                <form class="filterbar" method="GET" action="{{ route('erp.app', ['page' => 'configuration']) }}">
                    <input type="hidden" name="group" value="{{ $selectedConfigurationGroup }}">
                    <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('common.search') }}">
                    <button class="button button-secondary" type="submit">{{ __('common.search') }}</button>
                </form>
                @if($configurationFormFields !== [])
                    <details class="more-details" @if($selectedConfigurationRows->isEmpty()) open @endif>
                        <summary>{{ __('configuration.create_item') }}</summary>
                        <form class="form-grid" method="POST" action="{{ route('configuration.store', ['group' => $selectedConfigurationGroup]) }}" style="margin-top:12px">
                            @csrf
                            @foreach($configurationFormFields as $fieldName => $fieldMeta)
                                <x-configuration-field :name="$fieldName" :meta="$fieldMeta" />
                            @endforeach
                            <div class="form-actions field-wide"><button class="button" type="submit">{{ __('common.save') }}</button></div>
                        </form>
                    </details>
                @endif
                @if($selectedConfigurationRows->isEmpty())
                    <x-empty-state />
                @else
                    @php($columns = array_keys((array) $selectedConfigurationRows->first()))
                    <div class="table-scroll"><table><thead><tr>@foreach($columns as $column)<th>{{ __('field.'.$column) }}</th>@endforeach<th>{{ __('common.actions') }}</th></tr></thead><tbody>@foreach($selectedConfigurationRows as $row)<tr>@foreach($columns as $column)<td>{{ is_array($row->{$column}) ? implode(', ', $row->{$column}) : (is_bool($row->{$column}) ? ($row->{$column} ? __('common.yes') : __('common.no')) : $row->{$column}) }}</td>@endforeach<td><details><summary class="button button-ghost">{{ __('common.edit') }}</summary><form class="form-grid panel" method="POST" action="{{ route('configuration.update', ['group' => $selectedConfigurationGroup, 'id' => $row->id]) }}">@csrf @method('PATCH')@foreach($configurationFormFields as $fieldName => $fieldMeta)<x-configuration-field :name="$fieldName" :meta="$fieldMeta" :value="$row->{$fieldName} ?? null" />@endforeach<div class="form-actions field-wide"><button class="button" type="submit">{{ __('common.save') }}</button></div></form></details><form method="POST" action="{{ route('configuration.destroy', ['group' => $selectedConfigurationGroup, 'id' => $row->id]) }}" onsubmit="return confirm('{{ __('configuration.delete_confirm') }}')">@csrf @method('DELETE')<button class="button button-ghost" type="submit">{{ __('common.delete') }}</button></form></td></tr>@endforeach</tbody></table></div>
                @endif
            </section>
        @endif
    @elseif ($page === 'users-permissions')
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('nav.users_permissions') }}</p>
                <h1>{{ __('users.title') }}</h1>
                <p>{{ __('users.subtitle') }}</p>
            </div>
        </section>
        <div class="module-grid">
            @foreach ($adminResources as $resource)
                <x-module-card
                    icon="users"
                    :title="__($resource['label_key'])"
                    :description="__($resource['description_key']).' - '.$resource['count'].' '.__('common.items')"
                    :action="__('common.view')"
                    :href="$resource['url']"
                />
            @endforeach
        </div>
        @if ($selectedAdminResource)
            <section class="section table-card">
                <div class="section-header panel"><div><h2>{{ __('users.'.str_replace('-', '_', $selectedAdminResource)) }}</h2><p>{{ __('users.live_data_hint') }}</p></div></div>
                @if($selectedAdminRows->isEmpty())
                    <x-empty-state />
                @else
                    @php($columns = array_keys((array) $selectedAdminRows->first()))
                    <div class="table-scroll"><table><thead><tr>@foreach($columns as $column)<th>{{ __('field.'.$column) }}</th>@endforeach</tr></thead><tbody>@foreach($selectedAdminRows as $row)<tr>@foreach($columns as $column)<td>{{ is_bool($row->{$column}) ? ($row->{$column} ? __('common.yes') : __('common.no')) : $row->{$column} }}</td>@endforeach</tr>@endforeach</tbody></table></div>
                @endif
            </section>
        @endif
    @endif
@endsection
