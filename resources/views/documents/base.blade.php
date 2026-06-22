@php
    $rtl = $language === 'ar';
@endphp
<!doctype html>
<html lang="{{ $language }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('document-title')</title>
    <style>
        @font-face { font-family: DINNext; src: url('{{ $branding['font_data_uri'] }}') format('opentype'); font-weight: normal; }
        @page { size: {{ $page['width'] }}mm {{ $page['height'] }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #eef1f4; color: #333; font-family: DINNext, DejaVu Sans, Arial, sans-serif; }
        body { direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        .document-toolbar { position: sticky; top: 0; z-index: 10; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #071427; }
        .document-toolbar a, .document-toolbar button { border: 1px solid #fff; border-radius: 7px; padding: 7px 14px; color: #fff; background: transparent; font: inherit; text-decoration: none; cursor: pointer; }
        .document-toolbar .primary { color: #071427; background: #fff; }
        .document-page { position: relative; overflow: hidden; width: {{ $page['width'] }}mm; min-height: {{ $page['height'] }}mm; margin: 14px auto; padding: 8mm; background: #fff; }
        .watermark { position: absolute; inset: 21% 13%; z-index: 0; display: flex; align-items: center; justify-content: center; opacity: .045; transform: rotate(-18deg); }
        .watermark img { width: 70%; }
        .document-content { position: relative; z-index: 1; }
        .doc-header { display: grid; grid-template-columns: 1fr 1.4fr 1fr; gap: 8px; align-items: center; padding-bottom: 4mm; border-bottom: 2px solid {{ $branding['primary_color'] }}; }
        .doc-header-logo { text-align: center; }
        .doc-header-logo img { display: inline-block; width: 72mm; max-height: 27mm; object-fit: contain; }
        .doc-header-side { font-size: 9pt; line-height: 1.45; color: {{ $branding['secondary_color'] }}; }
        .doc-header-side.right { text-align: right; }
        .doc-header-side.left { text-align: left; }
        .doc-title { margin: 4mm 0 3mm; text-align: center; font-size: 17pt; color: {{ $branding['secondary_color'] }}; text-decoration: underline; text-decoration-color: {{ $branding['primary_color'] }}; }
        .doc-number { text-align: center; font-size: 9pt; color: #666; }
        .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2.2mm; margin: 3mm 0; }
        .meta-cell { min-height: 12mm; padding: 2mm; border: 1px solid #989b96; border-radius: 2mm; }
        .meta-cell.wide { grid-column: span 2; }
        .meta-label { display: block; margin-bottom: 1mm; color: #6f726d; font-size: 8pt; }
        .meta-value { font-size: 10pt; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin: 2mm 0; font-size: 8.5pt; }
        th, td { padding: 1.7mm 1.3mm; border: 1px solid #858883; vertical-align: top; }
        th { color: #fff; background: {{ $branding['secondary_color'] }}; font-weight: 700; }
        tbody tr:nth-child(even) { background: #faf8f2; }
        .numeric { text-align: right; white-space: nowrap; }
        .totals { width: 48%; margin-inline-start: auto; margin-top: 3mm; }
        .totals-row { display: flex; justify-content: space-between; gap: 8px; padding: 1.4mm 2mm; border-bottom: 1px solid #ccc; }
        .totals-row.grand { border: 2px solid {{ $branding['primary_color'] }}; color: #252724; font-size: 12pt; font-weight: 700; }
        .note-box { min-height: 15mm; margin-top: 3mm; padding: 2mm; border: 1px solid #a8aaa6; border-radius: 2mm; }
        .section-title { margin: 3mm 0 1.5mm; padding-bottom: .8mm; border-bottom: 1px solid {{ $branding['primary_color'] }}; font-size: 11pt; color: #4f524e; }
        .signature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8mm; margin-top: 8mm; font-size: 8.5pt; }
        .signature { padding-top: 8mm; border-top: 1px dotted #777; text-align: center; }
        .doc-footer { margin-top: 6mm; padding-top: 2mm; border-top: 1px solid {{ $branding['primary_color'] }}; text-align: center; color: #777; font-size: 7.5pt; }
        .rx-table td, .rx-table th { text-align: center; }
        .blank-area { min-height: 18mm; border-bottom: 1px dotted #aaa; }
        .page-break { page-break-before: always; break-before: page; }
        .compact { font-size: 8pt; }
        .thermal-page { padding: 4mm; }
        .thermal-page .doc-header { display: block; text-align: center; }
        .thermal-page .doc-header-logo img { width: 55mm; }
        .thermal-page table { font-size: 7pt; }
        .thermal-page .totals { width: 100%; }
        .glasses-page { padding: 4mm 7mm; }
        .glasses-page .doc-header { grid-template-columns: 1.2fr 1.7fr; padding-bottom: 2mm; }
        .glasses-page .doc-header-logo img { width: 70mm; }
        .glasses-page .doc-header-side.left { display: none; }
        @media print {
            html, body { background: #fff; }
            .document-toolbar { display: none !important; }
            .document-page { margin: 0; box-shadow: none; }
        }
        @if($isPdf)
            html, body { background: #fff; }
            .document-toolbar { display: none; }
            .document-page { margin: 0; }
        @endif
    </style>
    @stack('document-styles')
</head>
<body>
    @unless($isPdf)
        <nav class="document-toolbar">
            <button type="button" onclick="window.print()">{{ $t('Print', 'طباعة') }}</button>
            <a class="primary" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}">{{ $t('Download PDF', 'تحميل PDF') }}</a>
            <a href="{{ url()->previous() }}">{{ $t('Back', 'رجوع') }}</a>
        </nav>
    @endunless
    @yield('document')
</body>
</html>
