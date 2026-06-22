@php($brand = app(\App\Support\DocumentSettings::class)->all(request()->integer('branch_id') ?: null))
@if($brand['logo_path'])
    <img src="{{ asset($brand['logo_path']) }}" alt="{{ $brand['company_name'] }}" {{ $attributes->merge(['class' => 'brand-logo']) }}>
@else
    <span {{ $attributes->merge(['class' => 'brand-logo-fallback']) }}>{{ $brand['company_name'] }}</span>
@endif
