<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfigurationController extends Controller
{
    public const OPTION_GROUPS = [
        'product_types', 'brands', 'frame_types', 'lens_types', 'lens_materials', 'lens_coatings', 'lens_tints',
        'payment_methods', 'tax_settings', 'appointment_types', 'appointment_statuses', 'order_statuses',
        'order_priorities', 'lens_indexes', 'genders', 'document_types', 'exam_statuses', 'prescription_statuses',
        'whatsapp_template_categories', 'sale_modes', 'pickup_statuses', 'invoice_settings', 'receipt_settings',
        'prescription_print_settings', 'currency_settings', 'numbering_settings', 'accounting_settings',
        'sales_document_types', 'invoice_statuses', 'sales_order_statuses', 'order_document_types',
        'calendar_views', 'reminder_triggers',
        'order_active_statuses', 'order_lab_statuses', 'order_whatsapp_triggers',
    ];

    private const TABLE_GROUPS = [
        'company_profile' => 'companies',
        'branches' => 'branches',
        'locations' => 'inventory_locations',
        'product_categories' => 'product_categories',
        'suppliers' => 'suppliers',
        'whatsapp_templates' => 'whatsapp_templates',
        'chart_of_accounts' => 'accounting_accounts',
        'roles' => 'roles',
        'permissions' => 'permissions',
    ];

    public function index(Request $request, string $group)
    {
        $this->assertGroup($group);
        $query = $this->query($group, trim((string) $request->query('q', '')));

        return response()->json([
            'group' => $group,
            'data' => $query->limit(200)->get(),
            'filters' => $request->only('q'),
        ]);
    }

    public function store(Request $request, string $group)
    {
        $this->assertGroup($group);

        $id = DB::transaction(function () use ($request, $group): int {
            $now = now();
            $data = $this->validatedData($request, $group);
            $table = $this->table($group);
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $id = DB::table($table)->insertGetId($data);
            $this->syncRelations($request, $group, $id, $now);
            $this->audit('configuration.created', $table, $id, null, $data);

            return $id;
        });

        return $this->respond($request, $group, 'created', $id, __('Configuration item created.'));
    }

    public function update(Request $request, string $group, int $id)
    {
        $this->assertGroup($group);
        $table = $this->table($group);
        $before = DB::table($table)->where('id', $id)->first();
        if (! $before || ($this->isOptionGroup($group) && $before->group !== $group)) {
            abort(404);
        }

        DB::transaction(function () use ($request, $group, $id, $table, $before): void {
            $data = $this->validatedData($request, $group, $id);
            $data['updated_at'] = now();
            DB::table($table)->where('id', $id)->update($data);
            $this->syncRelations($request, $group, $id, $data['updated_at']);
            $this->audit('configuration.updated', $table, $id, (array) $before, $data);
        });

        return $this->respond($request, $group, 'updated', $id, __('Configuration item updated.'));
    }

    public function destroy(Request $request, string $group, int $id)
    {
        $this->assertGroup($group);
        $table = $this->table($group);
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row || ($this->isOptionGroup($group) && $row->group !== $group)) {
            abort(404);
        }

        $deactivate = $this->isOptionGroup($group)
            ? (bool) $row->is_system
            : in_array($group, ['branches', 'locations', 'suppliers', 'whatsapp_templates', 'chart_of_accounts'], true);

        if ($group === 'roles' && $row->slug === 'erp-admin') {
            throw ValidationException::withMessages(['configuration' => 'The ERP administrator role cannot be deleted.']);
        }
        if ($group === 'roles' && DB::table('role_user')->where('role_id', $id)->exists()) {
            throw ValidationException::withMessages(['configuration' => 'This role is assigned to users and cannot be deleted.']);
        }
        if ($group === 'permissions' && DB::table('permission_role')->where('permission_id', $id)->exists()) {
            throw ValidationException::withMessages(['configuration' => 'This permission is assigned to roles and cannot be deleted.']);
        }

        try {
            if ($deactivate) {
                DB::table($table)->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
                $status = 'deactivated';
            } else {
                DB::table($table)->where('id', $id)->delete();
                $status = 'deleted';
            }
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'configuration' => 'This item is already in use. Deactivate or replace it instead of deleting it.',
            ]);
        }

        $this->audit('configuration.'.$status, $table, $id, (array) $row, ['status' => $status]);

        return $this->respond($request, $group, $status, $id, __('Configuration item '.$status.'.'));
    }

    private function query(string $group, string $search)
    {
        if ($this->isOptionGroup($group)) {
            return DB::table('configuration_options')
                ->where('group', $group)
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('key', 'like', "%{$search}%")
                    ->orWhere('label_en', 'like', "%{$search}%")
                    ->orWhere('label_ar', 'like', "%{$search}%")))
                ->orderBy('sort_order')
                ->orderBy('label_en');
        }

        $table = $this->table($group);
        $columns = match ($group) {
            'company_profile' => ['name', 'legal_name', 'tax_number'],
            'branches', 'locations' => ['code', 'name'],
            'product_categories', 'suppliers' => ['name'],
            'whatsapp_templates' => ['name', 'slug', 'provider_template_name'],
            'chart_of_accounts' => ['code', 'name', 'type'],
            'roles' => ['name', 'slug'],
            'permissions' => ['name', 'slug', 'module'],
            default => [],
        };

        return DB::table($table)
            ->when($search !== '' && $columns !== [], function ($query) use ($columns, $search): void {
                $query->where(function ($inner) use ($columns, $search): void {
                    foreach ($columns as $index => $column) {
                        $index === 0
                            ? $inner->where($column, 'like', "%{$search}%")
                            : $inner->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy($this->orderColumn($group));
    }

    private function validatedData(Request $request, string $group, ?int $id = null): array
    {
        if ($this->isOptionGroup($group)) {
            $validated = $request->validate([
                'company_id' => ['nullable', 'integer', 'exists:companies,id'],
                'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
                'key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
                'label_en' => ['required', 'string', 'max:255'],
                'label_ar' => ['nullable', 'string', 'max:255'],
                'value' => ['nullable'],
                'value_type' => ['nullable', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
                'description_en' => ['nullable', 'string', 'max:2000'],
                'description_ar' => ['nullable', 'string', 'max:2000'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'is_active' => ['nullable', 'boolean'],
            ]);
            $companyId = $validated['company_id'] ?? $this->companyId();
            $branchId = $validated['branch_id'] ?? null;
            $duplicate = DB::table('configuration_options')
                ->where('group', $group)
                ->where('key', $validated['key'])
                ->where('company_id', $companyId)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereNull('branch_id'))
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['key' => 'This key already exists in the selected configuration group.']);
            }

            return [
                ...$validated,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'group' => $group,
                'value' => $this->serializedValue($validated['value'] ?? $validated['key']),
                'value_type' => $validated['value_type'] ?? 'string',
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'is_system' => $id ? (bool) DB::table('configuration_options')->where('id', $id)->value('is_system') : false,
            ];
        }

        $validated = $request->validate($this->rules($group, $id));
        foreach ($this->booleanFields($group) as $field) {
            $default = $id
                ? (bool) DB::table($this->table($group))->where('id', $id)->value($field)
                : in_array($field, ['is_active', 'is_sellable'], true);
            $validated[$field] = $request->boolean($field, $default);
        }
        if (in_array($group, ['branches', 'product_categories', 'suppliers', 'roles'], true)) {
            $validated['company_id'] = $validated['company_id'] ?? $this->companyId();
        }
        if ($group === 'chart_of_accounts') {
            $validated['company_id'] = $validated['company_id'] ?? $this->companyId();
            $validated['is_system'] = $id ? (bool) DB::table('accounting_accounts')->where('id', $id)->value('is_system') : false;
        }
        if ($group === 'whatsapp_templates') {
            $validated['company_id'] = $validated['company_id'] ?? $this->companyId();
            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        }

        unset($validated['permission_ids']);

        return $validated;
    }

    private function rules(string $group, ?int $id): array
    {
        return match ($group) {
            'company_profile' => [
                'name' => ['required', 'string', 'max:255'], 'legal_name' => ['nullable', 'string', 'max:255'],
                'tax_number' => ['nullable', 'string', 'max:255'], 'currency' => ['required', 'string', 'size:3'],
                'phone' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'],
            ],
            'branches' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'code' => ['required', 'string', 'max:255', Rule::unique('branches', 'code')->ignore($id)],
                'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'], 'is_active' => ['nullable', 'boolean'],
            ],
            'locations' => [
                'branch_id' => ['required', 'integer', 'exists:branches,id'], 'code' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'],
                'type' => ['required', 'string', 'max:255'], 'is_sellable' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
            ],
            'product_categories' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'parent_id' => ['nullable', 'integer', 'exists:product_categories,id', Rule::notIn(array_filter([$id]))],
                'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:255'],
            ],
            'suppliers' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'name' => ['required', 'string', 'max:255'], 'contact_person' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'],
                'tax_number' => ['nullable', 'string', 'max:255'], 'opening_balance' => ['nullable', 'numeric'], 'is_active' => ['nullable', 'boolean'],
            ],
            'whatsapp_templates' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', Rule::unique('whatsapp_templates', 'slug')->ignore($id)],
                'category' => ['required', 'string', 'max:255'], 'trigger_key' => ['nullable', 'string', 'max:255'], 'provider_template_name' => ['nullable', 'string', 'max:255'],
                'language_code' => ['required', 'string', 'max:20'], 'body' => ['required', 'string', 'max:4000'], 'footer' => ['nullable', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean'],
            ],
            'chart_of_accounts' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'parent_id' => ['nullable', 'integer', 'exists:accounting_accounts,id', Rule::notIn(array_filter([$id]))],
                'code' => ['required', 'string', 'max:255', Rule::unique('accounting_accounts', 'code')->ignore($id)], 'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])], 'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
                'is_cash' => ['nullable', 'boolean'], 'is_bank' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'description' => ['nullable', 'string', 'max:2000'],
            ],
            'roles' => [
                'company_id' => ['nullable', 'integer', 'exists:companies,id'], 'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($id)],
                'description' => ['nullable', 'string', 'max:2000'], 'permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
            ],
            'permissions' => [
                'module' => ['required', 'string', 'max:255'], 'action' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('permissions', 'slug')->ignore($id)],
            ],
            default => throw ValidationException::withMessages(['group' => 'Unsupported configuration group.']),
        };
    }

    private function syncRelations(Request $request, string $group, int $id, $now): void
    {
        if ($group !== 'roles' || ! $request->has('permission_ids')) {
            return;
        }
        DB::table('permission_role')->where('role_id', $id)->delete();
        foreach (array_unique(array_map('intval', (array) $request->input('permission_ids', []))) as $permissionId) {
            DB::table('permission_role')->insert(['role_id' => $id, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function booleanFields(string $group): array
    {
        return match ($group) {
            'branches', 'suppliers', 'whatsapp_templates' => ['is_active'],
            'locations' => ['is_sellable', 'is_active'],
            'chart_of_accounts' => ['is_cash', 'is_bank', 'is_active'],
            default => [],
        };
    }

    private function serializedValue(mixed $value): mixed
    {
        return is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function table(string $group): string
    {
        return $this->isOptionGroup($group) ? 'configuration_options' : self::TABLE_GROUPS[$group];
    }

    private function orderColumn(string $group): string
    {
        return match ($group) {
            'branches', 'locations', 'chart_of_accounts' => 'code',
            'roles', 'permissions', 'whatsapp_templates' => 'slug',
            default => 'name',
        };
    }

    private function isOptionGroup(string $group): bool
    {
        return in_array($group, self::OPTION_GROUPS, true);
    }

    private function assertGroup(string $group): void
    {
        abort_unless($this->isOptionGroup($group) || array_key_exists($group, self::TABLE_GROUPS), 404);
    }

    private function companyId(): int
    {
        return (int) DB::table('companies')->orderBy('id')->value('id');
    }

    private function audit(string $action, string $table, int $id, ?array $before, ?array $after): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(), 'action' => $action, 'auditable_type' => $table, 'auditable_id' => $id,
            'before_values' => $before ? json_encode($before) : null, 'after_values' => $after ? json_encode($after) : null,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function respond(Request $request, string $group, string $status, int $id, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['group' => $group, 'status' => $status, 'id' => $id], $status === 'created' ? 201 : 200);
        }

        return redirect()->route('erp.app', ['page' => 'configuration', 'group' => $group, 'lang' => app()->getLocale()])->with('status', $message);
    }
}
