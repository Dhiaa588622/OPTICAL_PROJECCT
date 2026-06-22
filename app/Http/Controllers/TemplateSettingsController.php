<?php

namespace App\Http\Controllers;

use App\Support\DocumentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TemplateSettingsController extends Controller
{
    public function edit(DocumentSettings $settings): View
    {
        return view('configuration.template-settings', [
            'branding' => $settings->all(request()->integer('branch_id') ?: null),
            'branches' => DB::table('branches')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'header_text_en' => ['nullable', 'string', 'max:500'],
            'header_text_ar' => ['nullable', 'string', 'max:500'],
            'footer_text_en' => ['nullable', 'string', 'max:500'],
            'footer_text_ar' => ['nullable', 'string', 'max:500'],
            'prepared_by_en' => ['required', 'string', 'max:100'],
            'prepared_by_ar' => ['required', 'string', 'max:100'],
            'approved_by_en' => ['required', 'string', 'max:100'],
            'approved_by_ar' => ['required', 'string', 'max:100'],
            'received_by_en' => ['required', 'string', 'max:100'],
            'received_by_ar' => ['required', 'string', 'max:100'],
            'paper_size' => ['required', 'in:a4,a5,invoice,receipt,thermal'],
            'template_language' => ['required', 'in:ar,en,bilingual'],
            'default_template' => ['required', 'in:focus,compact'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_watermark' => ['nullable', 'boolean'],
        ]);

        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchId = $validated['branch_id'] ?? null;
        unset($validated['branch_id'], $validated['logo']);
        $validated['show_watermark'] = $request->boolean('show_watermark') ? '1' : '0';

        if ($request->hasFile('logo')) {
            $directory = public_path('uploads/branding');
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            $filename = 'company-'.$companyId.'-'.now()->format('YmdHis').'.'.$request->file('logo')->extension();
            $request->file('logo')->move($directory, $filename);
            $validated['logo_path'] = 'uploads/branding/'.$filename;
        }

        DB::transaction(function () use ($validated, $companyId, $branchId, $request): void {
            foreach ($validated as $key => $value) {
                $query = DB::table('system_settings')->where('company_id', $companyId)->where('group', DocumentSettings::GROUP)->where('key', $key)
                    ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId), fn ($builder) => $builder->whereNull('branch_id'));
                $payload = ['value' => $value, 'is_active' => true, 'updated_at' => now()];
                if ($query->exists()) {
                    $query->update($payload);
                } else {
                    DB::table('system_settings')->insert([
                        ...$payload, 'company_id' => $companyId, 'branch_id' => $branchId, 'group' => DocumentSettings::GROUP,
                        'key' => $key, 'label' => str($key)->replace('_', ' ')->title(), 'value_type' => $key === 'show_watermark' ? 'boolean' : 'string',
                        'description' => 'Document template setting.', 'is_public' => true, 'created_at' => now(),
                    ]);
                }
            }

            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id, 'branch_id' => $branchId, 'action' => 'configuration.document_templates.updated',
                'auditable_type' => 'system_settings', 'auditable_id' => $companyId,
                'after_values' => json_encode(array_keys($validated)), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('status', __('Template settings saved.'));
    }
}
