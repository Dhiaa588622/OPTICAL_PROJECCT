@extends('documents.base')
@section('document-title', $t('Purchase Invoice', 'فاتورة شراء').' '.$record->receipt_number)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Purchase Invoice / Goods Receipt', 'فاتورة شراء / استلام بضاعة') }}</h1>
        <div class="meta-grid"><div class="meta-cell"><span class="meta-label">{{ $t('Receipt No.', 'رقم الاستلام') }}</span><span class="meta-value">{{ $record->receipt_number }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Supplier invoice', 'فاتورة المورد') }}</span><span class="meta-value">{{ $record->supplier_invoice_number ?: '-' }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('PO No.', 'رقم أمر الشراء') }}</span><span class="meta-value">{{ $record->po_number ?: '-' }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->received_on }}</span></div><div class="meta-cell wide"><span class="meta-label">{{ $t('Supplier', 'المورد') }}</span><span class="meta-value">{{ $record->supplier_name }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Branch', 'الفرع') }}</span><span class="meta-value">{{ $record->branch_name }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Status', 'الحالة') }}</span><span class="meta-value">{{ str($record->status)->title() }}</span></div></div>
        <table><thead><tr><th>#</th><th>{{ $t('SKU', 'الرمز') }}</th><th>{{ $t('Product', 'الصنف') }}</th><th>{{ $t('Received', 'المستلم') }}</th><th>{{ $t('Accepted', 'المقبول') }}</th><th>{{ $t('Rejected', 'المرفوض') }}</th><th>{{ $t('Unit cost', 'تكلفة الوحدة') }}</th><th>{{ $t('Total', 'الإجمالي') }}</th></tr></thead><tbody>@foreach($record->lines as $line)<tr><td>{{ $loop->iteration }}</td><td>{{ $line->sku }}</td><td>{{ $line->product_name }}</td><td>{{ number_format($line->received_quantity, 2) }}</td><td>{{ number_format($line->accepted_quantity, 2) }}</td><td>{{ number_format($line->rejected_quantity, 2) }}</td><td class="numeric">{{ number_format($line->unit_cost, 2) }}</td><td class="numeric">{{ number_format($line->accepted_quantity * $line->unit_cost, 2) }}</td></tr>@endforeach</tbody></table>
        <div class="totals"><div class="totals-row grand"><span>{{ $t('Purchase total', 'إجمالي الشراء') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->subtotal, 2) }}</strong></div></div>
        @if($record->notes)<div class="note-box"><strong>{{ $t('Notes', 'ملاحظات') }}:</strong> {{ $record->notes }}</div>@endif
        <div class="signature-grid"><div class="signature">{{ $branding['received_by_ar'] }} / {{ $branding['received_by_en'] }}<br>{{ $record->received_by_name }}</div><div class="signature">{{ $t('Storekeeper', 'أمين المخزن') }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
