@extends('documents.base')
@section('document-title', $t('Sales Invoice', 'فاتورة بيع').' '.$record->invoice_number)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Sales Invoice', 'فاتورة بيع') }}</h1>
        <div class="doc-number">{{ $t('Invoice No.', 'رقم الفاتورة') }}: <strong>{{ $record->invoice_number }}</strong></div>
        <div class="meta-grid">
            <div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->invoice_date }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Branch', 'الفرع') }}</span><span class="meta-value">{{ $record->branch_name }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Customer / Patient', 'العميل / المريض') }}</span><span class="meta-value">{{ $record->customer_name ?: $t('Walk-in customer', 'عميل نقدي') }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Phone', 'الهاتف') }}</span><span class="meta-value">{{ $record->customer_phone ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Cashier', 'الكاشير') }}</span><span class="meta-value">{{ $record->cashier_name ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Status', 'الحالة') }}</span><span class="meta-value">{{ str($record->status)->replace('_', ' ')->title() }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Sale type', 'نوع البيع') }}</span><span class="meta-value">{{ str($record->sale_mode)->replace('_', ' ')->title() }}</span></div>
        </div>
        <table>
            <thead><tr><th>#</th><th>{{ $t('Item code', 'رقم الصنف') }}</th><th>{{ $t('Item', 'اسم الصنف') }}</th><th>{{ $t('Description', 'المواصفات') }}</th><th>{{ $t('Qty', 'الكمية') }}</th><th>{{ $t('Unit price', 'سعر الوحدة') }}</th><th>{{ $t('Discount', 'الخصم') }}</th><th>{{ $t('Tax', 'الضريبة') }}</th><th>{{ $t('Total', 'الإجمالي') }}</th></tr></thead>
            <tbody>@foreach($record->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->sku ?: '-' }}</td><td>{{ $item->description }}</td><td>{{ str($item->item_type)->replace('_', ' ') }}</td><td class="numeric">{{ number_format($item->quantity, 2) }}</td><td class="numeric">{{ number_format($item->unit_price, 2) }}</td><td class="numeric">{{ number_format($item->discount_amount, 2) }}</td><td class="numeric">{{ number_format($item->tax_amount, 2) }}</td><td class="numeric">{{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="totals">
            <div class="totals-row"><span>{{ $t('Subtotal', 'المجموع') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->subtotal, 2) }}</strong></div>
            <div class="totals-row"><span>{{ $t('Discount', 'الخصم') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->discount_total, 2) }}</strong></div>
            <div class="totals-row"><span>{{ $t('Tax', 'الضريبة') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->tax_total, 2) }}</strong></div>
            <div class="totals-row grand"><span>{{ $t('Grand total', 'الإجمالي') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->grand_total, 2) }}</strong></div>
            <div class="totals-row"><span>{{ $t('Paid', 'المدفوع') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->paid_total, 2) }}</strong></div>
            <div class="totals-row"><span>{{ $t('Balance', 'المتبقي') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->balance_due, 2) }}</strong></div>
        </div>
        @if($record->payments->isNotEmpty())<div class="section-title">{{ $t('Payments', 'الدفعات') }}</div><table><thead><tr><th>{{ $t('Number', 'الرقم') }}</th><th>{{ $t('Method', 'الطريقة') }}</th><th>{{ $t('Date', 'التاريخ') }}</th><th>{{ $t('Amount', 'المبلغ') }}</th></tr></thead><tbody>@foreach($record->payments as $payment)<tr><td>{{ $payment->payment_number }}</td><td>{{ str($payment->payment_method)->replace('_', ' ')->title() }}</td><td>{{ $payment->paid_at }}</td><td class="numeric">{{ $branding['currency'] }} {{ number_format($payment->amount, 2) }}</td></tr>@endforeach</tbody></table>@endif
        @if($record->notes)<div class="note-box"><strong>{{ $t('Notes', 'ملاحظات') }}:</strong> {{ $record->notes }}</div>@endif
        <div class="signature-grid"><div class="signature">{{ $branding['prepared_by_ar'] }} / {{ $branding['prepared_by_en'] }}<br>{{ $record->cashier_name }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div><div class="signature">{{ $branding['received_by_ar'] }} / {{ $branding['received_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
