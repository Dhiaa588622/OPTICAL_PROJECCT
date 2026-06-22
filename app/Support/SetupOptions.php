<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupOptions
{
    public function options(string $group, array $fallback = []): Collection
    {
        if (! Schema::hasTable('configuration_options')) {
            return $this->fallback($fallback);
        }

        $labelColumn = app()->getLocale() === 'ar' ? 'label_ar' : 'label_en';
        $companyId = Schema::hasTable('companies') ? DB::table('companies')->orderBy('id')->value('id') : null;
        $branchId = request()?->integer('branch_id') ?: null;
        $rows = DB::table('configuration_options')
            ->select('key', 'value', $labelColumn.' as label', 'label_en')
            ->where('group', $group)
            ->where('is_active', true)
            ->when($companyId, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->where(function ($scope) use ($branchId): void {
                $scope->whereNull('branch_id');
                if ($branchId) {
                    $scope->orWhere('branch_id', $branchId);
                }
            })
            ->orderByRaw('case when company_id is null then 0 else 1 end')
            ->orderByRaw('case when branch_id is null then 0 else 1 end')
            ->orderBy('sort_order')
            ->orderBy($labelColumn)
            ->get();

        return $rows->mapWithKeys(function (object $row): array {
            return [
                $row->key => $row->label ?: $row->label_en ?: $row->key,
            ];
        });
    }

    public function keys(string $group, array $fallback = []): array
    {
        return $this->options($group, $fallback)->keys()->all();
    }

    public function label(string $group, ?string $key, array $fallback = []): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return (string) ($this->options($group, $fallback)->get($key)
            ?? str((string) $key)->replace(['_', '-'], ' ')->title());
    }

    public function rows(string $group): Collection
    {
        if (! Schema::hasTable('configuration_options')) {
            return collect();
        }

        $labelColumn = app()->getLocale() === 'ar' ? 'label_ar' : 'label_en';

        $companyId = Schema::hasTable('companies') ? DB::table('companies')->orderBy('id')->value('id') : null;

        return DB::table('configuration_options')
            ->select('id', 'group', 'key', $labelColumn.' as label', 'value', 'value_type', 'sort_order', 'is_active')
            ->where('group', $group)
            ->when($companyId, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->orderBy('sort_order')
            ->orderBy($labelColumn)
            ->get();
    }

    private function fallback(array $fallback): Collection
    {
        return collect($fallback)->mapWithKeys(fn (string $label, string|int $key): array => [(string) $key => $label]);
    }
}
