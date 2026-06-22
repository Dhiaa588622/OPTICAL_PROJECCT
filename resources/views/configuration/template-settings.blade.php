@extends('layouts.erp')

@section('title', __('Template Settings'))

@section('content')
<section class="page-header">
    <div><p class="eyebrow">{{ __('Configuration') }}</p><h1>{{ __('Template Settings') }}</h1><p>{{ __('Control the logo, company data, paper, language, headers, footers and signatures used by every printed document.') }}</p></div>
    <a class="button button-ghost" href="{{ route('erp.app', ['page' => 'configuration', 'lang' => app()->getLocale()]) }}">{{ __('Back') }}</a>
</section>
<section class="panel template-settings">
    <div class="template-brand-preview">
        @if($branding['logo_data_uri'])<img src="{{ $branding['logo_data_uri'] }}" alt="{{ $branding['company_name'] }}">@endif
        <div><strong>{{ $branding['company_name'] }}</strong><span>{{ $branding['address'] }}</span><span>{{ $branding['phone'] }}</span></div>
    </div>
    <form class="form-grid" method="POST" action="{{ route('configuration.templates.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="field"><label>{{ __('Branch override') }}</label><select name="branch_id"><option value="">{{ __('All branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Company logo') }}</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        @foreach(['company_name' => __('Company name override'), 'phone' => __('Phone override'), 'tax_number' => __('Tax number override')] as $key => $label)
            <div class="field"><label>{{ $label }}</label><input name="{{ $key }}" value="{{ old($key, $branding[$key]) }}"></div>
        @endforeach
        <div class="field field-wide"><label>{{ __('Address override') }}</label><textarea name="address">{{ old('address', $branding['address']) }}</textarea></div>
        @foreach(['header_text_en','header_text_ar','footer_text_en','footer_text_ar'] as $key)
            <div class="field"><label>{{ __(str($key)->replace('_', ' ')->title()->toString()) }}</label><textarea name="{{ $key }}">{{ old($key, $branding[$key]) }}</textarea></div>
        @endforeach
        @foreach(['prepared_by_en','prepared_by_ar','approved_by_en','approved_by_ar','received_by_en','received_by_ar'] as $key)
            <div class="field"><label>{{ __(str($key)->replace('_', ' ')->title()->toString()) }}</label><input name="{{ $key }}" required value="{{ old($key, $branding[$key]) }}"></div>
        @endforeach
        <div class="field"><label>{{ __('Default paper size') }}</label><select name="paper_size">@foreach(['a4','a5','invoice','receipt','thermal'] as $value)<option value="{{ $value }}" @selected(old('paper_size', $branding['paper_size']) === $value)>{{ strtoupper($value) }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Template language') }}</label><select name="template_language">@foreach(['bilingual','ar','en'] as $value)<option value="{{ $value }}" @selected(old('template_language', $branding['template_language']) === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Default template') }}</label><select name="default_template">@foreach(['focus','compact'] as $value)<option value="{{ $value }}" @selected(old('default_template', $branding['default_template']) === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Primary color') }}</label><input type="color" name="primary_color" value="{{ old('primary_color', $branding['primary_color']) }}"></div>
        <div class="field"><label>{{ __('Secondary color') }}</label><input type="color" name="secondary_color" value="{{ old('secondary_color', $branding['secondary_color']) }}"></div>
        <div class="field checkbox-field"><label><input type="checkbox" name="show_watermark" value="1" @checked(old('show_watermark', $branding['show_watermark']))> {{ __('Show optical watermark') }}</label></div>
        <div class="form-actions field-wide"><button class="button" type="submit">{{ __('Save template settings') }}</button></div>
    </form>
</section>
@endsection
