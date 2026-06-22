<?php

namespace App\Http\Controllers;

use App\Support\AccountingReportService;
use App\Support\SetupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErpController extends Controller
{
    private const PAGES = [
        'dashboard' => 'nav.dashboard',
        'patients' => 'nav.patients',
        'appointments' => 'nav.appointments',
        'optical-orders' => 'nav.optical_orders',
        'sales-pos' => 'nav.sales_pos',
        'inventory' => 'nav.inventory',
        'whatsapp' => 'nav.whatsapp',
        'accounting' => 'nav.accounting',
        'reports' => 'nav.reports',
        'configuration' => 'nav.configuration',
        'users-permissions' => 'nav.users_permissions',
        'search' => 'search.title',
        'workflows' => 'section.workflow',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';
        $page = match ($page) {
            'admin' => 'users-permissions',
            'settings' => 'configuration',
            default => $page,
        };

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        $databaseReady = $this->databaseReady();

        return view('erp.app', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => $databaseReady,
            ...$this->erpData($request, $databaseReady, $page),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! $this->databaseReady()) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $this->branchId($request);

        return response()->json([
            'business_date' => now()->toDateString(),
            'branch_id' => $branchId,
            'metrics' => $this->metrics($branchId),
            'worklists' => $this->worklists($branchId),
            'accounting' => $this->accountingSummary($branchId),
            'readiness' => $this->readinessChecks(),
        ]);
    }

    public function searchApi(Request $request)
    {
        if (! $this->databaseReady()) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'query' => $q,
            'branch_id' => $this->branchId($request),
            'searchable_fields' => ['patient name', 'phone', 'WhatsApp', 'SKU', 'barcode', 'order number', 'invoice number'],
            'results' => $this->globalSearch($q, $this->branchId($request)),
        ]);
    }

    public function workflowApi(Request $request)
    {
        if (! $this->databaseReady()) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        return response()->json([
            'branch_id' => $this->branchId($request),
            'workflows' => $this->workflowStatus($this->branchId($request)),
        ]);
    }

    public function reportApi(Request $request, string $report)
    {
        if (! array_key_exists($report, config('erp.reports'))) {
            abort(404);
        }

        return response()->json([
            'report' => $report,
            'title' => __('reports.'.$report),
            'branch_id' => $this->branchId($request),
            'generated_at' => now()->toDateTimeString(),
            'data' => $this->reportRows($report, $this->branchId($request))->values(),
        ]);
    }

    public function configurationApi(Request $request, ?string $group = null)
    {
        if (! $this->databaseReady()) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $locale = app()->getLocale();
        if ($group) {
            return response()->json([
                'group' => $group,
                'locale' => $locale,
                'data' => $this->configurationRows($group, $locale),
            ]);
        }

        return response()->json([
            'locale' => $locale,
            'groups' => $this->configurationGroups($locale),
        ]);
    }

    public function exportReport(Request $request, string $report): StreamedResponse|View
    {
        if (! array_key_exists($report, config('erp.reports'))) {
            abort(404);
        }

        $rows = $this->reportRows($report, $this->branchId($request))->values();
        $title = __('reports.'.$report);

        if ($request->query('format', 'print') !== 'csv') {
            return view('erp.print-report', [
                'title' => $title,
                'report' => $report,
                'rows' => $rows,
                'generatedAt' => now(),
                'branch' => $this->selectedBranch($this->branchId($request)),
            ]);
        }

        $filename = $report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            $first = (array) ($rows->first() ?? []);
            if ($first === []) {
                fputcsv($out, ['No data']);
                fclose($out);

                return;
            }

            fputcsv($out, array_keys($first));
            foreach ($rows as $row) {
                fputcsv($out, array_map(
                    fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value),
                    (array) $row
                ));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function erpData(Request $request, bool $databaseReady, string $page): array
    {
        $branchId = $this->branchId($request);
        $query = trim((string) $request->query('q', ''));

        return [
            'navigation' => config('erp.navigation'),
            'quickActions' => config('erp.quick_actions'),
            'reports' => config('erp.reports'),
            'branches' => $this->branches(),
            'selectedBranch' => $branchId,
            'selectedBranchRecord' => $this->selectedBranch($branchId),
            'query' => $query,
            'metrics' => $databaseReady ? $this->metrics($branchId) : [],
            'globalResults' => $databaseReady ? $this->globalSearch($query, $branchId) : [],
            'worklists' => $databaseReady ? $this->worklists($branchId) : [],
            'workflows' => $databaseReady ? $this->workflowStatus($branchId) : [],
            'accounting' => $databaseReady ? $this->accountingSummary($branchId) : [],
            'adminResources' => $databaseReady ? $this->adminResources() : collect(),
            'selectedAdminResource' => $request->query('resource'),
            'selectedAdminRows' => $databaseReady && $request->query('resource')
                ? $this->adminRows((string) $request->query('resource'))
                : collect(),
            'configurationGroups' => $databaseReady ? $this->configurationGroups(app()->getLocale()) : collect(),
            'selectedConfigurationGroup' => $request->query('group'),
            'selectedConfigurationRows' => $databaseReady && $request->query('group')
                ? $this->filterConfigurationRows($this->configurationRows((string) $request->query('group'), app()->getLocale()), $query)
                : collect(),
            'selectedConfigurationIsOption' => in_array((string) $request->query('group'), ConfigurationController::OPTION_GROUPS, true),
            'configurationBranches' => $databaseReady && Schema::hasTable('branches') ? DB::table('branches')->orderBy('name')->get() : collect(),
            'configurationPermissions' => $databaseReady && Schema::hasTable('permissions') ? DB::table('permissions')->orderBy('module')->orderBy('action')->get() : collect(),
            'configurationFormFields' => $this->configurationFormFields((string) $request->query('group')),
            'moduleCards' => $databaseReady ? $this->moduleCards($page) : [],
            'readiness' => $databaseReady ? $this->readinessChecks() : [],
        ];
    }

    private function databaseReady(): bool
    {
        return collect(['patients', 'products', 'sales_invoices', 'inventory_stock_levels', 'patient_appointments'])
            ->every(fn (string $table) => Schema::hasTable($table));
    }

    private function branchId(Request $request): ?int
    {
        $branchId = $request->integer('branch_id');

        return $branchId > 0 ? $branchId : null;
    }

    private function branches(): Collection
    {
        if (! Schema::hasTable('branches')) {
            return collect();
        }

        return DB::table('branches')->where('is_active', true)->orderBy('name')->get();
    }

    private function selectedBranch(?int $branchId): ?object
    {
        if (! $branchId || ! Schema::hasTable('branches')) {
            return null;
        }

        return DB::table('branches')->where('id', $branchId)->first();
    }

    private function adminResources(): Collection
    {
        $resources = [
            'users' => ['users.users', 'users.multi_user'],
            'roles' => ['users.roles', 'users.default_roles'],
            'permissions' => ['users.permissions', 'users.module_rules'],
            'branches' => ['users.branches', 'users.store_locations'],
            'audit-logs' => ['users.audit_logs', 'users.audit_history'],
        ];

        return collect($resources)->map(function (array $resource, string $key): array {
            $table = match ($key) {
                'audit-logs' => 'audit_logs',
                default => $key,
            };

            return [
                'key' => $key,
                'label_key' => $resource[0],
                'description_key' => $resource[1],
                'count' => Schema::hasTable($table) ? DB::table($table)->count() : 0,
                'url' => route('erp.app', ['page' => 'users-permissions', 'resource' => $key, 'lang' => app()->getLocale()]),
            ];
        })->values();
    }

    private function adminRows(string $resource): Collection
    {
        return match ($resource) {
            'users' => Schema::hasTable('users') ? DB::table('users')->select('id', 'name', 'email', 'created_at')->orderBy('name')->get() : collect(),
            'roles' => Schema::hasTable('roles') ? DB::table('roles')->select('id', 'name', 'slug', 'description')->orderBy('name')->get() : collect(),
            'permissions' => Schema::hasTable('permissions') ? DB::table('permissions')->select('id', 'name', 'slug', 'module', 'action')->orderBy('module')->orderBy('action')->get() : collect(),
            'branches' => Schema::hasTable('branches') ? DB::table('branches')->select('id', 'code', 'name', 'is_active')->orderBy('name')->get() : collect(),
            'audit-logs' => Schema::hasTable('audit_logs') ? DB::table('audit_logs')->select('id', 'action', 'auditable_type', 'auditable_id', 'created_at')->orderByDesc('id')->limit(100)->get() : collect(),
            default => collect(),
        };
    }

    private function configurationGroups(string $locale): Collection
    {
        $groups = [
            'company_profile' => 'companies',
            'branches' => 'branches',
            'locations' => 'inventory_locations',
            'product_categories' => 'product_categories',
            'suppliers' => 'suppliers',
            'whatsapp_templates' => 'whatsapp_templates',
            'chart_of_accounts' => 'accounting_accounts',
            'roles' => 'roles',
            'permissions' => 'permissions',
            ...array_fill_keys(ConfigurationController::OPTION_GROUPS, 'configuration_options'),
        ];

        return collect($groups)->map(fn (string $source, string $key) => [
            'key' => $key,
            'label' => __('configuration.'.$key),
            'label_key' => 'configuration.'.$key,
            'count' => $this->configurationCount($key, $source),
            'source' => $source,
            'url' => route('erp.app', ['page' => 'configuration', 'group' => $key, 'lang' => $locale]),
        ])->values();
    }

    private function configurationCount(string $key, string $source): int
    {
        if (! Schema::hasTable($source)) {
            return 0;
        }

        if ($source === 'configuration_options') {
            return DB::table('configuration_options')->where('group', $key)->count();
        }

        return DB::table($source)->count();
    }

    private function configurationRows(string $group, string $locale): Collection
    {
        $labelColumn = $locale === 'ar' ? 'label_ar' : 'label_en';

        if (Schema::hasTable('configuration_options') && DB::table('configuration_options')->where('group', $group)->exists()) {
            return DB::table('configuration_options')
                ->select('id', 'group', 'key', 'label_en', 'label_ar', $labelColumn.' as label', 'value', 'value_type', 'sort_order', 'is_system', 'is_active')
                ->where('group', $group)
                ->orderBy('sort_order')
                ->orderBy($labelColumn)
                ->get();
        }

        return match ($group) {
            'company_profile' => Schema::hasTable('companies')
                ? DB::table('companies')->select('id', 'name', 'legal_name', 'tax_number', 'currency', 'phone', 'email', 'address')->get()
                : collect(),
            'branches' => Schema::hasTable('branches')
                ? DB::table('branches')->select('id', 'code', 'name', 'phone', 'address', 'is_active')->orderBy('name')->get()
                : collect(),
            'locations' => Schema::hasTable('inventory_locations')
                ? DB::table('inventory_locations')->leftJoin('branches', 'branches.id', '=', 'inventory_locations.branch_id')->select('inventory_locations.id', 'inventory_locations.branch_id', 'inventory_locations.code', 'inventory_locations.name', 'inventory_locations.type', 'inventory_locations.is_sellable', 'branches.name as branch', 'inventory_locations.is_active')->orderBy('branches.name')->orderBy('inventory_locations.name')->get()
                : collect(),
            'product_categories' => Schema::hasTable('product_categories')
                ? DB::table('product_categories')->select('id', 'name', 'type', 'parent_id')->orderBy('name')->get()
                : collect(),
            'suppliers' => Schema::hasTable('suppliers')
                ? DB::table('suppliers')->select('id', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'opening_balance', 'is_active')->orderBy('name')->get()
                : collect(),
            'whatsapp_templates' => Schema::hasTable('whatsapp_templates')
                ? DB::table('whatsapp_templates')->select('id', 'slug', 'name', 'category', 'trigger_key', 'provider_template_name', 'language_code', 'body', 'footer', 'is_active')->orderBy('slug')->get()
                : collect(),
            'chart_of_accounts' => Schema::hasTable('accounting_accounts')
                ? DB::table('accounting_accounts')->select('id', 'parent_id', 'code', 'name', 'type', 'normal_balance', 'is_cash', 'is_bank', 'description', 'is_system', 'is_active')->orderBy('code')->get()
                : collect(),
            'roles' => $this->roleConfigurationRows(),
            'permissions' => Schema::hasTable('permissions')
                ? DB::table('permissions')->select('id', 'module', 'action', 'name', 'slug')->orderBy('module')->orderBy('action')->get()
                : collect(),
            default => collect(),
        };
    }

    private function configurationFormFields(string $group): array
    {
        if (in_array($group, ConfigurationController::OPTION_GROUPS, true)) {
            return [
                'key' => ['required' => true], 'label_en' => ['required' => true], 'label_ar' => [],
                'value' => [], 'value_type' => ['type' => 'select', 'options' => ['string' => 'String', 'integer' => 'Integer', 'decimal' => 'Decimal', 'boolean' => 'Boolean', 'json' => 'JSON']],
                'sort_order' => ['type' => 'number'], 'is_active' => ['type' => 'checkbox'],
            ];
        }

        $branches = Schema::hasTable('branches') ? DB::table('branches')->orderBy('name')->pluck('name', 'id')->all() : [];
        $categories = Schema::hasTable('product_categories') ? DB::table('product_categories')->orderBy('name')->pluck('name', 'id')->all() : [];
        $accounts = Schema::hasTable('accounting_accounts') ? DB::table('accounting_accounts')->orderBy('code')->get()->mapWithKeys(fn ($account) => [$account->id => $account->code.' - '.$account->name])->all() : [];

        return match ($group) {
            'company_profile' => ['name' => ['required' => true], 'legal_name' => [], 'tax_number' => [], 'currency' => ['required' => true], 'phone' => [], 'email' => ['type' => 'email'], 'address' => ['type' => 'textarea']],
            'branches' => ['code' => ['required' => true], 'name' => ['required' => true], 'phone' => [], 'address' => ['type' => 'textarea'], 'is_active' => ['type' => 'checkbox']],
            'locations' => ['branch_id' => ['type' => 'select', 'options' => $branches, 'required' => true], 'code' => ['required' => true], 'name' => ['required' => true], 'type' => ['required' => true], 'is_sellable' => ['type' => 'checkbox'], 'is_active' => ['type' => 'checkbox']],
            'product_categories' => ['parent_id' => ['type' => 'select', 'options' => ['' => __('common.not_set')] + $categories], 'name' => ['required' => true], 'type' => ['required' => true]],
            'suppliers' => ['name' => ['required' => true], 'contact_person' => [], 'phone' => [], 'email' => ['type' => 'email'], 'address' => ['type' => 'textarea'], 'tax_number' => [], 'opening_balance' => ['type' => 'number'], 'is_active' => ['type' => 'checkbox']],
            'whatsapp_templates' => ['name' => ['required' => true], 'slug' => [], 'category' => ['required' => true], 'trigger_key' => [], 'provider_template_name' => [], 'language_code' => ['required' => true], 'body' => ['type' => 'textarea', 'required' => true], 'footer' => [], 'is_active' => ['type' => 'checkbox']],
            'chart_of_accounts' => ['parent_id' => ['type' => 'select', 'options' => ['' => __('common.not_set')] + $accounts], 'code' => ['required' => true], 'name' => ['required' => true], 'type' => ['type' => 'select', 'options' => array_combine(['asset', 'liability', 'equity', 'revenue', 'expense'], ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']), 'required' => true], 'normal_balance' => ['type' => 'select', 'options' => ['debit' => 'Debit', 'credit' => 'Credit'], 'required' => true], 'is_cash' => ['type' => 'checkbox'], 'is_bank' => ['type' => 'checkbox'], 'description' => ['type' => 'textarea'], 'is_active' => ['type' => 'checkbox']],
            'roles' => ['name' => ['required' => true], 'slug' => ['required' => true], 'description' => ['type' => 'textarea'], 'permission_ids' => ['type' => 'multiselect', 'options' => $this->configurationPermissions()]],
            'permissions' => ['module' => ['required' => true], 'action' => ['required' => true], 'name' => ['required' => true], 'slug' => ['required' => true]],
            default => [],
        };
    }

    private function filterConfigurationRows(Collection $rows, string $search): Collection
    {
        if ($search === '') {
            return $rows;
        }

        $needle = Str::lower($search);

        return $rows->filter(fn (object $row) => Str::contains(Str::lower(json_encode($row) ?: ''), $needle))->values();
    }

    private function roleConfigurationRows(): Collection
    {
        if (! Schema::hasTable('roles')) {
            return collect();
        }

        return DB::table('roles')->select('id', 'name', 'slug', 'description')->orderBy('name')->get()->each(function (object $role): void {
            $role->permission_ids = DB::table('permission_role')->where('role_id', $role->id)->pluck('permission_id')->map(fn ($id) => (int) $id)->all();
        });
    }

    private function configurationPermissions(): array
    {
        if (! Schema::hasTable('permissions')) {
            return [];
        }

        return DB::table('permissions')->orderBy('module')->orderBy('action')->get()->mapWithKeys(fn ($permission) => [
            $permission->id => $permission->module.' / '.$permission->name,
        ])->all();
    }

    private function moduleCards(string $page): array
    {
        $cards = [
            'patients' => ['workspace.patients.title', 'workspace.patients.subtitle', 'workspace.patients.action', route('patients.app', ['page' => 'patient-create'])],
            'appointments' => ['workspace.appointments.title', 'workspace.appointments.subtitle', 'workspace.appointments.action', route('appointments.app', ['page' => 'create'])],
            'optical-orders' => ['workspace.optical_orders.title', 'workspace.optical_orders.subtitle', 'workspace.optical_orders.action', route('optical-orders.app', ['page' => 'create'])],
            'sales-pos' => ['workspace.sales_pos.title', 'workspace.sales_pos.subtitle', 'workspace.sales_pos.action', route('sales.app', ['page' => 'pos'])],
            'inventory' => ['workspace.inventory.title', 'workspace.inventory.subtitle', 'workspace.inventory.action', route('inventory.app', ['page' => 'goods-receiving'])],
            'whatsapp' => ['workspace.whatsapp.title', 'workspace.whatsapp.subtitle', 'workspace.whatsapp.action', route('whatsapp.app', ['page' => 'inbox'])],
            'accounting' => ['workspace.accounting.title', 'workspace.accounting.subtitle', 'workspace.accounting.action', route('erp.app', ['page' => 'accounting'])],
        ];

        if (! array_key_exists($page, $cards)) {
            return [];
        }

        [$title, $subtitle, $action, $url] = $cards[$page];

        return [[
            'title_key' => $title,
            'subtitle_key' => $subtitle,
            'action_key' => $action,
            'url' => $url,
        ]];
    }

    private function metrics(?int $branchId): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $todaySales = $this->tableSum('sales_invoices', 'grand_total', function ($query) use ($today, $branchId): void {
            $query->whereDate('invoice_date', $today)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        });

        $monthlyRevenue = $this->tableSum('sales_invoices', 'grand_total', function ($query) use ($monthStart, $today, $branchId): void {
            $query->whereBetween('invoice_date', [$monthStart, $today])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        });

        $netProfit = $this->tableSum('sales_invoices', 'gross_profit', function ($query) use ($monthStart, $today, $branchId): void {
            $query->whereBetween('invoice_date', [$monthStart, $today])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        });

        $inventoryValue = Schema::hasTable('inventory_stock_levels')
            ? (float) DB::table('inventory_stock_levels')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->sum(DB::raw('qty_on_hand * average_cost'))
            : 0.0;

        return [
            'today_sales' => round($todaySales, 2),
            'today_appointments' => $this->tableCount('patient_appointments', function ($query) use ($today, $branchId): void {
                $query->whereDate('appointment_at', $today)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'pending_optical_orders' => $this->tableCount('optical_orders', function ($query) use ($branchId): void {
                $query->whereIn('status', $this->activeOrderStatuses())
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'ready_for_pickup' => $this->tableCount('optical_orders', function ($query) use ($branchId): void {
                $query->where('status', 'ready_for_pickup')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'low_stock_products' => $this->tableCount('inventory_stock_levels', function ($query) use ($branchId): void {
                $query->whereRaw('(qty_on_hand - qty_reserved) <= reorder_point')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'unpaid_invoices' => $this->tableCount('sales_invoices', function ($query) use ($branchId): void {
                $query->where('balance_due', '>', 0)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'whatsapp_unread' => Schema::hasTable('whatsapp_conversations')
                ? (int) DB::table('whatsapp_conversations')
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->sum('unread_count')
                : 0,
            'cashier_closing_open' => $this->tableCount('sales_cash_sessions', function ($query) use ($branchId): void {
                $query->where('status', 'open')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            }),
            'monthly_revenue' => round($monthlyRevenue, 2),
            'net_profit' => round($netProfit, 2),
            'inventory_value' => round($inventoryValue, 2),
        ];
    }

    private function globalSearch(string $q, ?int $branchId): array
    {
        if ($q === '') {
            return [];
        }

        return [
            'patients' => $this->searchPatients($q),
            'products' => $this->searchProducts($q, $branchId),
            'optical_orders' => $this->searchOpticalOrders($q, $branchId),
            'invoices' => $this->searchInvoices($q, $branchId),
            'appointments' => $this->searchAppointments($q, $branchId),
            'whatsapp' => $this->searchWhatsApp($q, $branchId),
        ];
    }

    private function searchPatients(string $q): Collection
    {
        if (! Schema::hasTable('patients')) {
            return collect();
        }

        return DB::table('patients')
            ->selectRaw('"Patient" as type, id, patient_code as code, full_name as title, coalesce(phone, whatsapp_number, "") as subtitle, is_active as status')
            ->where(function ($query) use ($q): void {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('patient_code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('whatsapp_number', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/patients/profile?patient_id='.$row->id), $row->status ? __('common.active') : __('common.inactive')));
    }

    private function searchProducts(string $q, ?int $branchId): Collection
    {
        if (! Schema::hasTable('products')) {
            return collect();
        }

        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('inventory_stock_levels', function ($join) use ($branchId): void {
                $join->on('inventory_stock_levels.product_id', '=', 'products.id');
                if ($branchId) {
                    $join->where('inventory_stock_levels.branch_id', '=', $branchId);
                }
            })
            ->selectRaw('"Product" as type, products.id, products.sku as code, products.name as title, coalesce(products.brand, product_categories.name, products.type) as subtitle, coalesce(sum(inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved), 0) as available')
            ->where(function ($query) use ($q): void {
                $query->where('products.name', 'like', "%{$q}%")
                    ->orWhere('products.sku', 'like', "%{$q}%")
                    ->orWhere('products.barcode', 'like', "%{$q}%")
                    ->orWhere('products.brand', 'like', "%{$q}%")
                    ->orWhere('product_categories.name', 'like', "%{$q}%");
            })
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.brand', 'product_categories.name', 'products.type')
            ->orderBy('products.name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/inventory/products?q='.urlencode($q)), __('search.available').': '.number_format((float) $row->available, 0)));
    }

    private function searchOpticalOrders(string $q, ?int $branchId): Collection
    {
        if (! Schema::hasTable('optical_orders')) {
            return collect();
        }

        return DB::table('optical_orders')
            ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->leftJoin('products', 'products.id', '=', 'optical_orders.frame_product_id')
            ->selectRaw('"Optical Order" as type, optical_orders.id, optical_orders.order_number as code, patients.full_name as title, optical_orders.status as subtitle, optical_orders.outstanding_amount')
            ->where(function ($query) use ($q): void {
                $query->where('optical_orders.order_number', 'like', "%{$q}%")
                    ->orWhere('patients.full_name', 'like', "%{$q}%")
                    ->orWhere('patients.phone', 'like', "%{$q}%")
                    ->orWhere('patients.whatsapp_number', 'like', "%{$q}%")
                    ->orWhere('products.sku', 'like', "%{$q}%")
                    ->orWhere('products.barcode', 'like', "%{$q}%");
            })
            ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
            ->orderByDesc('optical_orders.id')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/optical-orders/details?order_id='.$row->id), $this->statusLabel($row->subtitle)));
    }

    private function searchInvoices(string $q, ?int $branchId): Collection
    {
        if (! Schema::hasTable('sales_invoices')) {
            return collect();
        }

        return DB::table('sales_invoices')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
            ->selectRaw('"Invoice" as type, sales_invoices.id, sales_invoices.invoice_number as code, coalesce(sales_customers.name, "Walk-in customer") as title, sales_invoices.status as subtitle, sales_invoices.balance_due')
            ->where(function ($query) use ($q): void {
                $query->where('sales_invoices.invoice_number', 'like', "%{$q}%")
                    ->orWhere('sales_customers.name', 'like', "%{$q}%")
                    ->orWhere('sales_customers.phone', 'like', "%{$q}%");
            })
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->orderByDesc('sales_invoices.id')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/sales/print?invoice_id='.$row->id), __('search.balance').': '.config('erp.module.currency', 'SAR').' '.number_format((float) $row->balance_due, 2)));
    }

    private function searchAppointments(string $q, ?int $branchId): Collection
    {
        if (! Schema::hasTable('patient_appointments')) {
            return collect();
        }

        return DB::table('patient_appointments')
            ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
            ->selectRaw('"Appointment" as type, patient_appointments.id, patient_appointments.appointment_number as code, patients.full_name as title, patient_appointments.status as subtitle, patient_appointments.appointment_at')
            ->where(function ($query) use ($q): void {
                $query->where('patient_appointments.appointment_number', 'like', "%{$q}%")
                    ->orWhere('patients.full_name', 'like', "%{$q}%")
                    ->orWhere('patients.phone', 'like', "%{$q}%")
                    ->orWhere('patients.whatsapp_number', 'like', "%{$q}%");
            })
            ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
            ->orderByDesc('patient_appointments.appointment_at')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/appointments/details?appointment_id='.$row->id), $this->statusLabel($row->subtitle).' - '.substr((string) $row->appointment_at, 0, 16)));
    }

    private function searchWhatsApp(string $q, ?int $branchId): Collection
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return collect();
        }

        return DB::table('whatsapp_conversations')
            ->leftJoin('patients', 'patients.id', '=', 'whatsapp_conversations.patient_id')
            ->selectRaw('"WhatsApp" as type, whatsapp_conversations.id, whatsapp_conversations.conversation_number as code, coalesce(patients.full_name, whatsapp_conversations.contact_name, whatsapp_conversations.whatsapp_number) as title, whatsapp_conversations.status as subtitle, whatsapp_conversations.unread_count')
            ->where(function ($query) use ($q): void {
                $query->where('whatsapp_conversations.conversation_number', 'like', "%{$q}%")
                    ->orWhere('whatsapp_conversations.whatsapp_number', 'like', "%{$q}%")
                    ->orWhere('whatsapp_conversations.contact_name', 'like', "%{$q}%")
                    ->orWhere('patients.full_name', 'like', "%{$q}%")
                    ->orWhere('patients.phone', 'like', "%{$q}%")
                    ->orWhere('patients.whatsapp_number', 'like', "%{$q}%");
            })
            ->when($branchId, fn ($query) => $query->where('whatsapp_conversations.branch_id', $branchId))
            ->orderByDesc('whatsapp_conversations.last_message_at')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->searchResult($row, url('/whatsapp/conversation?conversation_id='.$row->id), $row->unread_count.' '.__('search.unread')));
    }

    private function searchResult(object $row, string $url, string $meta): array
    {
        return [
            'type' => $row->type,
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'subtitle' => $row->subtitle,
            'meta' => $meta,
            'url' => $url,
        ];
    }

    private function worklists(?int $branchId): array
    {
        $today = now()->toDateString();

        return [
            'appointments' => Schema::hasTable('patient_appointments')
                ? DB::table('patient_appointments')
                    ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
                    ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
                    ->select('patient_appointments.id', 'patient_appointments.appointment_number', 'patient_appointments.appointment_at', 'patient_appointments.status', 'patients.full_name', 'patients.phone', 'branches.name as branch_name')
                    ->whereDate('patient_appointments.appointment_at', $today)
                    ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
                    ->orderBy('patient_appointments.appointment_at')
                    ->limit(8)
                    ->get()
                : collect(),
            'ready_orders' => $this->reportRows('ready-pickup', $branchId)->take(8),
            'low_stock' => $this->reportRows('low-stock', $branchId)->take(8),
            'unpaid_invoices' => $this->reportRows('unpaid-invoices', $branchId)->take(8),
            'unread_whatsapp' => $this->reportRows('whatsapp-unread', $branchId)->take(8),
        ];
    }

    private function workflowStatus(?int $branchId): array
    {
        $raw = [
            'patient_to_order' => [
                'Patient' => $this->tableCount('patients') > 0,
                'Appointment' => $this->tableCount('patient_appointments', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Eye Exam' => $this->tableCount('patient_eye_exams', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Prescription' => $this->tableCount('patient_prescriptions', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Optical Order' => $this->tableCount('optical_orders', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'WhatsApp Confirmation' => $this->tableCount('patient_whatsapp_messages', fn ($q) => $q->where('context_type', 'optical_order')) > 0,
            ],
            'order_to_invoice' => [
                'Order' => $this->tableCount('optical_orders', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Stock Reservation' => $this->tableCount('inventory_reservations', fn ($q) => $q->where('status', 'active')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Deposit' => $this->tableCount('optical_order_payments') > 0,
                'Lab Status' => $this->tableCount('optical_order_status_events', fn ($q) => $q->whereIn('to_status', ['sent_to_lab', 'in_lab', 'quality_check'])) > 0,
                'Ready for Pickup' => $this->tableCount('optical_orders', fn ($q) => $q->where('status', 'ready_for_pickup')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Final Payment' => $this->tableCount('optical_orders', fn ($q) => $q->where('outstanding_amount', '<=', 0)->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Invoice' => $this->tableCount('sales_invoices', fn ($q) => $q->where('sale_mode', 'prescription_order')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
            ],
            'purchase_to_accounting' => [
                'Purchase Order' => $this->tableCount('inventory_purchase_orders', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Goods Receipt' => $this->tableCount('inventory_goods_receipts', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Stock Increase' => $this->tableCount('inventory_stock_movements', fn ($q) => $q->where('direction', 'in')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Inventory Report' => $this->tableCount('inventory_stock_levels', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Accounting Entry' => $this->tableCount('accounting_journals', fn ($q) => $q->where('source_type', 'inventory_goods_receipt')) > 0,
            ],
            'pos_to_accounting' => [
                'POS Sale' => $this->tableCount('sales_invoices', fn ($q) => $q->where('sale_mode', 'ready_product')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Payment' => $this->tableCount('sales_payments', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Invoice' => $this->tableCount('sales_invoices', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Stock Reduction' => $this->tableCount('inventory_stock_movements', fn ($q) => $q->where('direction', 'out')->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'Accounting Entry' => $this->tableCount('accounting_journals', fn ($q) => $q->where('source_type', 'sales_invoice')) > 0,
            ],
            'invoice_to_reminder' => [
                'Invoice Created' => $this->tableCount('sales_invoices', fn ($q) => $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
                'WhatsApp Message' => $this->tableCount('patient_whatsapp_messages', fn ($q) => $q->whereIn('context_type', ['invoice', 'sales_invoice'])) > 0,
                'Payment Reminder' => $this->tableCount('whatsapp_automation_rules', fn ($q) => $q->where('trigger_key', 'payment_overdue')) > 0,
                'Patient Balance Update' => $this->tableCount('sales_invoices', fn ($q) => $q->where('balance_due', '>=', 0)->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId))) > 0,
            ],
        ];

        return collect(config('erp.workflows'))
            ->map(function (array $workflow) use ($raw): array {
                $steps = collect($workflow['steps'])
                    ->map(fn (string $step) => [
                        'label' => $step,
                        'label_key' => 'workflow.step.'.Str::of($step)->snake(),
                        'done' => (bool) ($raw[$workflow['key']][$step] ?? false),
                    ])
                    ->values()
                    ->all();

                $done = collect($steps)->where('done', true)->count();

                return [
                    'key' => $workflow['key'],
                    'title' => $workflow['title'],
                    'title_key' => 'workflow.title.'.$workflow['key'],
                    'steps' => $steps,
                    'progress' => count($steps) > 0 ? round(($done / count($steps)) * 100) : 0,
                    'status' => $done === count($steps) ? 'Ready' : ($done > 0 ? 'In progress' : 'Needs data'),
                    'status_key' => $done === count($steps) ? 'common.ready' : ($done > 0 ? 'common.in_progress' : 'common.needs_data'),
                ];
            })
            ->all();
    }

    private function accountingSummary(?int $branchId): array
    {
        if (! Schema::hasTable('accounting_journals')) {
            return [
                'ready' => false,
                'accounts' => 0,
                'journals' => 0,
                'unbalanced' => 0,
                'month_debit' => 0,
                'month_credit' => 0,
                'open_closings' => 0,
                'drafts' => collect(),
                'accounts_list' => collect(),
                'monthly_closings' => collect(),
                'recent_journals' => collect(),
            ];
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $journals = DB::table('accounting_journals')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        return [
            'ready' => true,
            'accounts' => DB::table('accounting_accounts')->count(),
            'journals' => (clone $journals)->count(),
            'unbalanced' => (clone $journals)->whereRaw('abs(total_debit - total_credit) > 0.009')->count(),
            'month_debit' => round((float) (clone $journals)->where('journal_date', '>=', $monthStart)->sum('total_debit'), 2),
            'month_credit' => round((float) (clone $journals)->where('journal_date', '>=', $monthStart)->sum('total_credit'), 2),
            'open_closings' => Schema::hasTable('accounting_daily_closings')
                ? DB::table('accounting_daily_closings')->where('status', 'open')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->count()
                : 0,
            'accounts_list' => DB::table('accounting_accounts')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'drafts' => (clone $journals)->where('status', 'draft')->orderByDesc('journal_date')->get(['id', 'journal_number', 'journal_date', 'description', 'total_debit', 'status']),
            'monthly_closings' => Schema::hasTable('accounting_monthly_closings')
                ? DB::table('accounting_monthly_closings')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->orderByDesc('year')->orderByDesc('month')->limit(6)->get()
                : collect(),
            'recent_journals' => (clone $journals)
                ->leftJoin('branches', 'branches.id', '=', 'accounting_journals.branch_id')
                ->select('accounting_journals.id', 'accounting_journals.journal_number', 'accounting_journals.journal_date', 'accounting_journals.description', 'accounting_journals.total_debit', 'accounting_journals.status', 'branches.name as branch_name')
                ->orderByDesc('accounting_journals.journal_date')
                ->orderByDesc('accounting_journals.id')
                ->limit(8)
                ->get(),
        ];
    }

    private function readinessChecks(): array
    {
        $checks = [
            'demo_branch' => Schema::hasTable('branches') && DB::table('branches')->count() > 0,
            'demo_products' => Schema::hasTable('products') && DB::table('products')->count() > 0 && Schema::hasTable('inventory_stock_levels') && DB::table('inventory_stock_levels')->count() > 0,
            'demo_patients' => Schema::hasTable('patients') && DB::table('patients')->count() > 0 && Schema::hasTable('patient_prescriptions') && DB::table('patient_prescriptions')->count() > 0,
            'roles_permissions' => Schema::hasTable('roles') && DB::table('roles')->whereIn('slug', ['erp-admin', 'store-manager', 'cashier'])->count() >= 3,
            'chart_accounts' => Schema::hasTable('accounting_accounts') && DB::table('accounting_accounts')->count() >= 8,
            'whatsapp_settings' => Schema::hasTable('whatsapp_settings') && DB::table('whatsapp_settings')->exists() && Schema::hasTable('whatsapp_templates') && DB::table('whatsapp_templates')->count() > 0,
            'system_settings' => Schema::hasTable('system_settings') && DB::table('system_settings')->count() > 0,
            'audit_logs' => Schema::hasTable('audit_logs') && DB::table('audit_logs')->count() > 0,
            'balanced_journals' => Schema::hasTable('accounting_journals') && DB::table('accounting_journals')->whereRaw('abs(total_debit - total_credit) > 0.009')->count() === 0,
            'report_exports' => true,
        ];

        return collect(config('erp.readiness_checks'))
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'done' => (bool) ($checks[$key] ?? false),
            ])
            ->values()
            ->all();
    }

    private function reportRows(string $report, ?int $branchId): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        $today = now()->toDateString();

        return match ($report) {
            'sales-today' => DB::table('sales_invoices')
                ->leftJoin('branches', 'branches.id', '=', 'sales_invoices.branch_id')
                ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                ->select('sales_invoices.invoice_number', 'sales_invoices.invoice_date', 'branches.name as branch', 'sales_customers.name as customer', 'sales_invoices.status', 'sales_invoices.grand_total', 'sales_invoices.paid_total', 'sales_invoices.balance_due')
                ->whereDate('sales_invoices.invoice_date', $today)
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->orderByDesc('sales_invoices.id')
                ->get(),
            'appointments-today' => DB::table('patient_appointments')
                ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
                ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
                ->select('patient_appointments.appointment_number', 'patient_appointments.appointment_at', 'branches.name as branch', 'patients.full_name as patient', 'patients.phone', 'patient_appointments.status', 'patient_appointments.purpose')
                ->whereDate('patient_appointments.appointment_at', $today)
                ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
                ->orderBy('patient_appointments.appointment_at')
                ->get(),
            'pending-orders' => DB::table('optical_orders')
                ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
                ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
                ->select('optical_orders.order_number', 'patients.full_name as patient', 'patients.phone', 'branches.name as branch', 'suppliers.name as lab', 'optical_orders.status', 'optical_orders.expected_delivery_date', 'optical_orders.outstanding_amount')
                ->whereIn('optical_orders.status', $this->activeOrderStatuses())
                ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
                ->orderBy('optical_orders.expected_delivery_date')
                ->get(),
            'ready-pickup' => DB::table('optical_orders')
                ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
                ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
                ->select('optical_orders.order_number', 'patients.full_name as patient', 'patients.phone', 'patients.whatsapp_number', 'branches.name as branch', 'optical_orders.ready_at', 'optical_orders.outstanding_amount', 'optical_orders.payment_status')
                ->where('optical_orders.status', 'ready_for_pickup')
                ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
                ->orderByDesc('optical_orders.ready_at')
                ->get(),
            'low-stock' => DB::table('inventory_stock_levels')
                ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                ->leftJoin('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                ->selectRaw('products.sku, products.name as product, products.brand, branches.name as branch, inventory_stock_levels.qty_on_hand, inventory_stock_levels.qty_reserved, (inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) as available_stock, inventory_stock_levels.reorder_point')
                ->whereRaw('(inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) <= inventory_stock_levels.reorder_point')
                ->when($branchId, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
                ->orderBy('available_stock')
                ->get(),
            'unpaid-invoices' => DB::table('sales_invoices')
                ->leftJoin('branches', 'branches.id', '=', 'sales_invoices.branch_id')
                ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                ->select('sales_invoices.invoice_number', 'sales_invoices.invoice_date', 'branches.name as branch', 'sales_customers.name as customer', 'sales_customers.phone', 'sales_invoices.grand_total', 'sales_invoices.paid_total', 'sales_invoices.balance_due')
                ->where('sales_invoices.balance_due', '>', 0)
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->orderByDesc('sales_invoices.balance_due')
                ->get(),
            'whatsapp-unread' => Schema::hasTable('whatsapp_conversations')
                ? DB::table('whatsapp_conversations')
                    ->leftJoin('patients', 'patients.id', '=', 'whatsapp_conversations.patient_id')
                    ->leftJoin('users', 'users.id', '=', 'whatsapp_conversations.assigned_to')
                    ->select('whatsapp_conversations.conversation_number', 'whatsapp_conversations.whatsapp_number', 'whatsapp_conversations.contact_name', 'patients.full_name as patient', 'users.name as assigned_to', 'whatsapp_conversations.status', 'whatsapp_conversations.unread_count', 'whatsapp_conversations.last_message_at')
                    ->where('whatsapp_conversations.unread_count', '>', 0)
                    ->when($branchId, fn ($query) => $query->where('whatsapp_conversations.branch_id', $branchId))
                    ->orderByDesc('whatsapp_conversations.last_message_at')
                    ->get()
                : collect(),
            'inventory-valuation' => DB::table('inventory_stock_levels')
                ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                ->leftJoin('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                ->selectRaw('products.sku, products.name as product, products.type, branches.name as branch, inventory_stock_levels.qty_on_hand, inventory_stock_levels.average_cost, (inventory_stock_levels.qty_on_hand * inventory_stock_levels.average_cost) as cost_value, (inventory_stock_levels.qty_on_hand * products.retail_price) as retail_value')
                ->when($branchId, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
                ->orderByDesc('cost_value')
                ->get(),
            'accounting-journals' => Schema::hasTable('accounting_journals')
                ? DB::table('accounting_journals')
                    ->leftJoin('branches', 'branches.id', '=', 'accounting_journals.branch_id')
                    ->select('accounting_journals.journal_number', 'accounting_journals.journal_date', 'branches.name as branch', 'accounting_journals.source_type', 'accounting_journals.description', 'accounting_journals.status', 'accounting_journals.total_debit', 'accounting_journals.total_credit')
                    ->when($branchId, fn ($query) => $query->where('accounting_journals.branch_id', $branchId))
                    ->orderByDesc('accounting_journals.journal_date')
                    ->orderByDesc('accounting_journals.id')
                    ->get()
                : collect(),
            'trial-balance' => app(AccountingReportService::class)->trialBalance($branchId, request('date_from'), request('date_to')),
            'general-ledger' => app(AccountingReportService::class)->ledger(
                (int) (request('account_id') ?: DB::table('accounting_accounts')->where('is_active', true)->orderBy('code')->value('id')),
                $branchId,
                request('date_from'),
                request('date_to'),
            ),
            'profit-and-loss' => app(AccountingReportService::class)->profitAndLoss($branchId, request('date_from'), request('date_to')),
            'balance-sheet' => app(AccountingReportService::class)->balanceSheet($branchId, request('date_to')),
            default => collect(),
        };
    }

    private function activeOrderStatuses(): array
    {
        return app(SetupOptions::class)->keys('order_active_statuses');
    }

    private function tableCount(string $table, ?callable $callback = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function tableSum(string $table, string $column, ?callable $callback = null): float
    {
        if (! Schema::hasTable($table)) {
            return 0.0;
        }

        $query = DB::table($table);
        if ($callback) {
            $callback($query);
        }

        return (float) $query->sum($column);
    }

    private function statusLabel(?string $status): string
    {
        $key = 'status.'.(string) $status;
        $translated = __($key);

        return $translated === $key ? str((string) $status)->replace('_', ' ')->title()->toString() : $translated;
    }
}
