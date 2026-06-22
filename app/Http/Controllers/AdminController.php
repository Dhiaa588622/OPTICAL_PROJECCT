<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $this->assertResource($resource);
        $query = $this->query($resource, $request);
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $total = (clone $query)->count();

        return response()->json([
            'resource' => $resource,
            'data' => (clone $query)->offset(($page - 1) * $perPage)->limit($perPage)->get(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'filters' => $request->only(['q', 'status', 'branch_id']),
        ]);
    }

    public function show(string $resource, int $id)
    {
        $this->assertResource($resource);
        $row = DB::table($this->table($resource))->where('id', $id)->first();
        if (! $row) {
            abort(404);
        }

        if ($resource === 'users') {
            $row->roles = DB::table('roles')
                ->join('role_user', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $id)
                ->select('roles.id', 'roles.name', 'roles.slug')
                ->get();
            $row->branches = DB::table('branches')
                ->join('branch_user', 'branch_user.branch_id', '=', 'branches.id')
                ->where('branch_user.user_id', $id)
                ->select('branches.id', 'branches.name', 'branches.code', 'branch_user.is_default')
                ->get();
        }

        return response()->json(['resource' => $resource, 'data' => $row]);
    }

    public function store(Request $request, string $resource)
    {
        $this->assertResource($resource);

        $id = match ($resource) {
            'users' => $this->createUser($request),
            'roles' => $this->createRole($request),
            'branches' => $this->createBranch($request),
            'settings' => $this->saveSetting($request),
            default => throw ValidationException::withMessages(['resource' => 'This admin resource is read-only.']),
        };

        return response()->json([
            'resource' => $resource,
            'status' => 'created',
            'id' => $id,
            'data' => DB::table($this->table($resource))->where('id', $id)->first(),
        ], 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->assertResource($resource);

        $row = match ($resource) {
            'users' => $this->updateUser($request, $id),
            'roles' => $this->updateRole($request, $id),
            'branches' => $this->updateSimple($request, $resource, $id, ['code', 'name', 'phone', 'address', 'is_active']),
            'settings' => $this->updateSetting($request, $id),
            default => throw ValidationException::withMessages(['resource' => 'This admin resource is read-only.']),
        };

        return response()->json(['resource' => $resource, 'status' => 'updated', 'data' => $row]);
    }

    private function query(string $resource, Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return match ($resource) {
            'users' => DB::table('users')
                ->select('id', 'name', 'email', 'created_at', 'updated_at')
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
                ->orderBy('name'),
            'roles' => DB::table('roles')
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"))
                ->orderBy('name'),
            'permissions' => DB::table('permissions')
                ->when($q !== '', fn ($query) => $query->where('module', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"))
                ->orderBy('module')
                ->orderBy('action'),
            'branches' => DB::table('branches')
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                ->orderBy('name'),
            'companies' => DB::table('companies')->orderBy('name'),
            'settings' => DB::table('system_settings')
                ->when($q !== '', fn ($query) => $query
                    ->where('group', 'like', "%{$q}%")
                    ->orWhere('key', 'like', "%{$q}%")
                    ->orWhere('label', 'like', "%{$q}%"))
                ->orderBy('group')
                ->orderBy('key'),
            'audit-logs' => DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->leftJoin('branches', 'branches.id', '=', 'audit_logs.branch_id')
                ->select('audit_logs.*', 'users.name as user_name', 'branches.name as branch_name')
                ->when($q !== '', fn ($query) => $query->where('audit_logs.action', 'like', "%{$q}%")->orWhere('audit_logs.auditable_type', 'like', "%{$q}%"))
                ->orderByDesc('audit_logs.id'),
            default => abort(404),
        };
    }

    private function createUser(Request $request): int
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role_ids' => ['nullable', 'array'],
            'branch_ids' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($validated): int {
            $now = now();
            $id = DB::table('users')->insertGetId([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->syncUserRolesAndBranches($id, $validated['role_ids'] ?? [], $validated['branch_ids'] ?? [], $now);
            $this->audit('admin.user.created', 'user', $id, null, ['email' => $validated['email']], $now);

            return $id;
        });
    }

    private function updateUser(Request $request, int $id): object
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (! $user) {
            abort(404);
        }
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        DB::transaction(function () use ($request, $validated, $id, $user): void {
            $now = now();
            $data = array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'updated_at' => $now,
            ], fn ($value) => $value !== null);
            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }
            DB::table('users')->where('id', $id)->update($data);
            if ($request->has('role_ids') || $request->has('branch_ids')) {
                $this->syncUserRolesAndBranches($id, $validated['role_ids'] ?? [], $validated['branch_ids'] ?? [], $now);
            }
            $auditData = $data;
            if (array_key_exists('password', $auditData)) {
                $auditData['password'] = '[changed]';
            }
            $this->audit('admin.user.updated', 'user', $id, null, $auditData, $now, (array) $user);
        });

        return DB::table('users')->where('id', $id)->first();
    }

    private function createRole(Request $request): int
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($validated): int {
            $now = now();
            $id = DB::table('roles')->insertGetId([
                'company_id' => DB::table('companies')->orderBy('id')->value('id'),
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->syncRolePermissions($id, $validated['permission_ids'] ?? [], $now);
            $this->audit('admin.role.created', 'role', $id, null, ['slug' => $validated['slug']], $now);

            return $id;
        });
    }

    private function updateRole(Request $request, int $id): object
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (! $role) {
            abort(404);
        }
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        DB::transaction(function () use ($request, $validated, $id, $role): void {
            $now = now();
            $data = array_filter([
                'name' => $validated['name'] ?? null,
                'slug' => $validated['slug'] ?? null,
                'description' => $validated['description'] ?? null,
                'updated_at' => $now,
            ], fn ($value) => $value !== null);
            DB::table('roles')->where('id', $id)->update($data);
            if ($request->has('permission_ids')) {
                $this->syncRolePermissions($id, $validated['permission_ids'] ?? [], $now);
            }
            $this->audit('admin.role.updated', 'role', $id, null, $data, $now, (array) $role);
        });

        return DB::table('roles')->where('id', $id)->first();
    }

    private function createBranch(Request $request): int
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $now = now();
        $id = DB::table('branches')->insertGetId([
            'company_id' => DB::table('companies')->orderBy('id')->value('id'),
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit('admin.branch.created', 'branch', $id, $id, ['code' => $validated['code'], 'name' => $validated['name']], $now);

        return $id;
    }

    private function saveSetting(Request $request): int
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable'],
            'value_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $payload = [
            'company_id' => $validated['company_id'] ?? DB::table('companies')->orderBy('id')->value('id'),
            'branch_id' => $validated['branch_id'] ?? null,
            'group' => $validated['group'],
            'key' => $validated['key'],
            'label' => $validated['label'] ?? null,
            'value' => array_key_exists('value', $validated)
                ? (is_scalar($validated['value']) || $validated['value'] === null ? $validated['value'] : json_encode($validated['value']))
                : null,
            'value_type' => $validated['value_type'] ?? 'string',
            'description' => $validated['description'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_active' => $request->boolean('is_active', true),
            'updated_at' => $now,
        ];

        $existing = DB::table('system_settings')
            ->where('company_id', $payload['company_id'])
            ->where(function ($query) use ($payload): void {
                $payload['branch_id']
                    ? $query->where('branch_id', $payload['branch_id'])
                    : $query->whereNull('branch_id');
            })
            ->where('group', $payload['group'])
            ->where('key', $payload['key'])
            ->first();

        if ($existing) {
            DB::table('system_settings')->where('id', $existing->id)->update($payload);
            $this->audit('admin.setting.updated', 'system_setting', (int) $existing->id, $payload['branch_id'], $payload, $now, (array) $existing);

            return (int) $existing->id;
        }

        $payload['created_at'] = $now;
        $id = DB::table('system_settings')->insertGetId($payload);
        $this->audit('admin.setting.created', 'system_setting', $id, $payload['branch_id'], $payload, $now);

        return $id;
    }

    private function updateSetting(Request $request, int $id): object
    {
        $setting = DB::table('system_settings')->where('id', $id)->first();
        if (! $setting) {
            abort(404);
        }

        $data = array_intersect_key($request->all(), array_flip([
            'group',
            'key',
            'label',
            'value',
            'value_type',
            'description',
            'company_id',
            'branch_id',
            'is_public',
            'is_active',
        ]));

        if (array_key_exists('value', $data) && ! is_scalar($data['value']) && $data['value'] !== null) {
            $data['value'] = json_encode($data['value']);
        }
        $data['updated_at'] = now();

        DB::table('system_settings')->where('id', $id)->update($data);
        $this->audit('admin.setting.updated', 'system_setting', $id, $data['branch_id'] ?? $setting->branch_id, $data, $data['updated_at'], (array) $setting);

        return DB::table('system_settings')->where('id', $id)->first();
    }

    private function updateSimple(Request $request, string $resource, int $id, array $allowed): object
    {
        $table = $this->table($resource);
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            abort(404);
        }

        $data = array_intersect_key($request->all(), array_flip($allowed));
        $data['updated_at'] = now();
        DB::table($table)->where('id', $id)->update($data);
        $this->audit('admin.'.$resource.'.updated', $this->auditableType($resource), $id, $data['branch_id'] ?? null, $data, $data['updated_at'], (array) $row);

        return DB::table($table)->where('id', $id)->first();
    }

    private function syncUserRolesAndBranches(int $userId, array $roleIds, array $branchIds, $now): void
    {
        DB::table('role_user')->where('user_id', $userId)->delete();
        foreach ($roleIds as $roleId) {
            DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('branch_user')->where('user_id', $userId)->delete();
        foreach ($branchIds as $index => $branchId) {
            DB::table('branch_user')->insert(['user_id' => $userId, 'branch_id' => $branchId, 'is_default' => $index === 0, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function syncRolePermissions(int $roleId, array $permissionIds, $now): void
    {
        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function audit(string $action, string $type, int $id, ?int $branchId, ?array $after, $now, ?array $before = null): void
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

    private function assertResource(string $resource): void
    {
        if (! array_key_exists($resource, $this->tables())) {
            abort(404);
        }
    }

    private function auditableType(string $resource): string
    {
        return match ($resource) {
            'branches' => 'branch',
            'settings' => 'system_setting',
            'audit-logs' => 'audit_log',
            default => rtrim(str_replace('-', '_', $resource), 's'),
        };
    }

    private function table(string $resource): string
    {
        return $this->tables()[$resource] ?? abort(404);
    }

    private function tables(): array
    {
        return [
            'users' => 'users',
            'roles' => 'roles',
            'permissions' => 'permissions',
            'branches' => 'branches',
            'companies' => 'companies',
            'settings' => 'system_settings',
            'audit-logs' => 'audit_logs',
        ];
    }
}
