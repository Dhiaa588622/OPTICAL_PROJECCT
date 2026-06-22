<?php

use App\Support\DocumentSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $companyId = Schema::hasTable('companies') ? DB::table('companies')->orderBy('id')->value('id') : null;
        $settings = [
            'logo_path' => ['Company logo', 'branding/focus-logo.jpg'],
            'company_name' => ['Company name override', ''],
            'address' => ['Address override', ''],
            'phone' => ['Phone override', ''],
            'tax_number' => ['Tax number override', ''],
            'header_text_en' => ['English header', ''],
            'header_text_ar' => ['Arabic header', ''],
            'footer_text_en' => ['English footer', 'Clear vision for a better life'],
            'footer_text_ar' => ['Arabic footer', 'رؤية واضحة لحياة أجمل'],
            'prepared_by_en' => ['Prepared by label', 'Prepared by'],
            'prepared_by_ar' => ['Arabic prepared by label', 'إعداد'],
            'approved_by_en' => ['Approved by label', 'Approved by'],
            'approved_by_ar' => ['Arabic approved by label', 'اعتماد'],
            'received_by_en' => ['Received by label', 'Received by'],
            'received_by_ar' => ['Arabic received by label', 'المستلم'],
            'paper_size' => ['Default paper size', 'a4'],
            'template_language' => ['Template language', 'bilingual'],
            'default_template' => ['Default print template', 'focus'],
            'primary_color' => ['Primary color', '#c9a55d'],
            'secondary_color' => ['Secondary color', '#73756f'],
            'show_watermark' => ['Show optical watermark', '1'],
        ];

        foreach ($settings as $key => [$label, $value]) {
            $exists = DB::table('system_settings')->where('group', DocumentSettings::GROUP)->where('key', $key)
                ->where(function ($query) use ($companyId): void {
                    $companyId ? $query->where('company_id', $companyId) : $query->whereNull('company_id');
                })->whereNull('branch_id')->exists();
            if ($exists) {
                continue;
            }
            DB::table('system_settings')->insert([
                'company_id' => $companyId, 'branch_id' => null, 'group' => DocumentSettings::GROUP,
                'key' => $key, 'label' => $label, 'value' => $value, 'value_type' => $key === 'show_watermark' ? 'boolean' : 'string',
                'description' => 'Controls branded HTML, browser print, and PDF documents.', 'is_public' => true, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('group', DocumentSettings::GROUP)->delete();
        }
    }
};
