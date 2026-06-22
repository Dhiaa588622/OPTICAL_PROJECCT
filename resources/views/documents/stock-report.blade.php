@extends('documents.base')
@section('document-title', $t('Stock Report', 'تقرير المخزون'))
@section('document')
<main class="document-page compact">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Stock and Valuation Report', 'تقرير المخزون والتقييم') }}</h1>
        <div class="doc-number">{{ $t('Generated', 'تاريخ الإصدار') }}: {{ now()->format('Y-m-d H:i') }}</div>
        <table><thead><tr><th>#</th><th>{{ $t('SKU', 'الرمز') }}</th><th>{{ $t('Product', 'الصنف') }}</th><th>{{ $t('Type', 'النوع') }}</th><th>{{ $t('Brand', 'الماركة') }}</th><th>{{ $t('Branch', 'الفرع') }}</th><th>{{ $t('Location', 'الموقع') }}</th><th>{{ $t('On hand', 'المتوفر') }}</th><th>{{ $t('Reserved', 'المحجوز') }}</th><th>{{ $t('Avg. cost', 'متوسط التكلفة') }}</th><th>{{ $t('Value', 'القيمة') }}</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row->sku }}</td><td>{{ $row->product }}</td><td>{{ $row->type }}</td><td>{{ $row->brand }}</td><td>{{ $row->branch }}</td><td>{{ $row->location }}</td><td class="numeric">{{ number_format($row->qty_on_hand, 2) }}</td><td class="numeric">{{ number_format($row->qty_reserved, 2) }}</td><td class="numeric">{{ number_format($row->average_cost, 2) }}</td><td class="numeric">{{ number_format($row->stock_value, 2) }}</td></tr>@endforeach<tr><th colspan="10">{{ $t('Total inventory value', 'إجمالي قيمة المخزون') }}</th><th class="numeric">{{ $branding['currency'] }} {{ number_format($rows->sum('stock_value'), 2) }}</th></tr></tbody></table>
        <div class="signature-grid"><div class="signature">{{ $t('Prepared by', 'إعداد') }}</div><div class="signature">{{ $t('Inventory manager', 'مدير المخزون') }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
