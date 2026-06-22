<header class="doc-header">
    <div class="doc-header-side right">
        @if($branding['header_text_ar'])<div>{{ $branding['header_text_ar'] }}</div>@endif
        @if($branding['branch_name'])<strong>{{ $branding['branch_name'] }}</strong>@endif
        @if($branding['address'])<div>{{ $branding['address'] }}</div>@endif
    </div>
    <div class="doc-header-logo">
        @if($branding['logo_data_uri'])<img src="{{ $branding['logo_data_uri'] }}" alt="{{ $branding['company_name'] }}">@else<strong>{{ $branding['company_name'] }}</strong>@endif
    </div>
    <div class="doc-header-side left">
        @if($branding['header_text_en'])<div>{{ $branding['header_text_en'] }}</div>@endif
        @if($branding['phone'])<div>{{ $t('Phone', 'الهاتف') }}: {{ $branding['phone'] }}</div>@endif
        @if($branding['tax_number'])<div>{{ $t('Tax No.', 'الرقم الضريبي') }}: {{ $branding['tax_number'] }}</div>@endif
    </div>
</header>
