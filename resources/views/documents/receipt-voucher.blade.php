@extends('documents.base')
@section('document-title', $t('Receipt Voucher', 'سند قبض').' '.$record->payment_number)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $record->direction === 'out' ? $t('Payment Voucher', 'سند صرف') : $t('Cash Receipt Voucher', 'سند قبض نقداً') }}</h1>
        <div class="meta-grid">
            <div class="meta-cell"><span class="meta-label">{{ $t('Number', 'الرقم') }}</span><span class="meta-value">{{ $record->payment_number }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->paid_at }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Amount', 'المبلغ') }}</span><span class="meta-value">{{ $branding['currency'] }} {{ number_format($record->amount, 2) }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $record->direction === 'out' ? $t('Paid to', 'صرفنا إلى') : $t('Received from', 'استلمنا من') }}</span><span class="meta-value">{{ $record->customer_name ?: $t('Walk-in customer', 'عميل نقدي') }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Payment method', 'طريقة الدفع') }}</span><span class="meta-value">{{ str($record->payment_method)->replace('_', ' ')->title() }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Invoice', 'الفاتورة') }}</span><span class="meta-value">{{ $record->invoice_number ?: '-' }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('For', 'وذلك مقابل') }}</span><span class="meta-value">{{ $record->notes ?: ($record->invoice_number ? $t('Payment against invoice', 'دفعة على الفاتورة').' '.$record->invoice_number : '-') }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Remaining balance', 'المبلغ المتبقي') }}</span><span class="meta-value">{{ $branding['currency'] }} {{ number_format($record->balance_due ?? 0, 2) }}</span></div>
        </div>
        <div class="signature-grid"><div class="signature">{{ $branding['prepared_by_ar'] }} / {{ $branding['prepared_by_en'] }}</div><div class="signature">{{ $branding['received_by_ar'] }} / {{ $branding['received_by_en'] }}<br>{{ $record->received_by_name }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
