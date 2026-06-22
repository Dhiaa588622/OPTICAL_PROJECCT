<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DocumentSettings
{
    public const GROUP = 'document_templates';

    public function all(?int $branchId = null): array
    {
        $branch = $branchId && Schema::hasTable('branches')
            ? DB::table('branches')->where('id', $branchId)->first()
            : null;
        $companyId = $branch?->company_id ?: (Schema::hasTable('companies') ? DB::table('companies')->orderBy('id')->value('id') : null);
        $company = $companyId ? DB::table('companies')->where('id', $companyId)->first() : null;
        $values = $this->values($companyId, $branchId);

        $logoPath = trim((string) ($values['logo_path'] ?? 'branding/focus-logo.jpg'));
        $nameOverride = trim((string) ($values['company_name'] ?? ''));
        $addressOverride = trim((string) ($values['address'] ?? ''));
        $phoneOverride = trim((string) ($values['phone'] ?? ''));
        $taxOverride = trim((string) ($values['tax_number'] ?? ''));

        return [
            'company_id' => $companyId,
            'company_name' => $nameOverride ?: ($company?->name ?? config('app.name')),
            'legal_name' => $company?->legal_name,
            'address' => $addressOverride ?: ($branch?->address ?: ($company?->address ?? null)),
            'phone' => $phoneOverride ?: ($branch?->phone ?: ($company?->phone ?? null)),
            'email' => $company?->email,
            'tax_number' => $taxOverride ?: ($company?->tax_number ?? null),
            'currency' => $company?->currency ?: config('erp.module.currency', 'SAR'),
            'branch_name' => $branch?->name,
            'logo_path' => $logoPath,
            'logo_data_uri' => $this->publicFileDataUri($logoPath),
            'font_data_uri' => $this->publicFileDataUri('fonts/DINNextLTArabic-Regular-2.otf'),
            'header_text_en' => $values['header_text_en'] ?? '',
            'header_text_ar' => $values['header_text_ar'] ?? '',
            'footer_text_en' => $values['footer_text_en'] ?? 'Clear vision for a better life',
            'footer_text_ar' => $values['footer_text_ar'] ?? 'رؤية واضحة لحياة أجمل',
            'prepared_by_en' => $values['prepared_by_en'] ?? 'Prepared by',
            'prepared_by_ar' => $values['prepared_by_ar'] ?? 'إعداد',
            'approved_by_en' => $values['approved_by_en'] ?? 'Approved by',
            'approved_by_ar' => $values['approved_by_ar'] ?? 'اعتماد',
            'received_by_en' => $values['received_by_en'] ?? 'Received by',
            'received_by_ar' => $values['received_by_ar'] ?? 'المستلم',
            'paper_size' => $values['paper_size'] ?? 'a4',
            'template_language' => $values['template_language'] ?? 'bilingual',
            'default_template' => $values['default_template'] ?? 'focus',
            'primary_color' => $values['primary_color'] ?? '#c9a55d',
            'secondary_color' => $values['secondary_color'] ?? '#73756f',
            'show_watermark' => filter_var($values['show_watermark'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    public function logoUrl(?int $branchId = null): string
    {
        $path = $this->all($branchId)['logo_path'];

        return asset($path ?: 'branding/focus-logo.jpg');
    }

    private function values(?int $companyId, ?int $branchId): array
    {
        if (! Schema::hasTable('system_settings')) {
            return [];
        }

        $query = DB::table('system_settings')
            ->where('group', self::GROUP)
            ->where('is_active', true)
            ->where(function ($scope) use ($companyId): void {
                $companyId ? $scope->where('company_id', $companyId)->orWhereNull('company_id') : $scope->whereNull('company_id');
            })
            ->where(function ($scope) use ($branchId): void {
                $branchId ? $scope->where('branch_id', $branchId)->orWhereNull('branch_id') : $scope->whereNull('branch_id');
            })
            ->orderByRaw('case when branch_id is null then 0 else 1 end')
            ->orderByRaw('case when company_id is null then 0 else 1 end');

        $values = [];
        foreach ($query->get(['key', 'value']) as $setting) {
            $values[$setting->key] = $setting->value;
        }

        return $values;
    }

    private function publicFileDataUri(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $path = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if (! is_file($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg', 'jfif' => 'image/jpeg',
            'otf' => 'font/otf',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
