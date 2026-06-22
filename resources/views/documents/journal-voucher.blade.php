@extends('documents.base')
@php($voucherTitle = match($record->voucher_type) {'receipt' => $t('Receipt Voucher', 'سند قبض'), 'payment' => $t('Payment Voucher', 'سند صرف'), default => $t('Journal Voucher', 'سند قيد')})
@section('document-title', $voucherTitle.' '.$record->journal_number)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $voucherTitle }}</h1>
        <div class="meta-grid">
            <div class="meta-cell"><span class="meta-label">{{ $t('Voucher No.', 'رقم السند') }}</span><span class="meta-value">{{ $record->journal_number }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->journal_date }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Branch', 'الفرع') }}</span><span class="meta-value">{{ $record->branch_name ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Status', 'الحالة') }}</span><span class="meta-value">{{ str($record->status)->title() }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Reference', 'المرجع') }}</span><span class="meta-value">{{ $record->source_type ? $record->source_type.' #'.$record->source_id : '-' }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Description', 'البيان') }}</span><span class="meta-value">{{ $record->description }}</span></div>
        </div>
        <table><thead><tr><th>#</th><th>{{ $t('Account code', 'رمز الحساب') }}</th><th>{{ $t('Account', 'الحساب') }}</th><th>{{ $t('Description', 'البيان') }}</th><th>{{ $t('Debit', 'مدين') }}</th><th>{{ $t('Credit', 'دائن') }}</th></tr></thead><tbody>@foreach($record->lines as $line)<tr><td>{{ $loop->iteration }}</td><td>{{ $line->account_code }}</td><td>{{ $line->account_name }}</td><td>{{ $line->description }}</td><td class="numeric">{{ number_format($line->debit, 2) }}</td><td class="numeric">{{ number_format($line->credit, 2) }}</td></tr>@endforeach<tr><th colspan="4">{{ $t('Total', 'الإجمالي') }}</th><th class="numeric">{{ $branding['currency'] }} {{ number_format($record->total_debit, 2) }}</th><th class="numeric">{{ $branding['currency'] }} {{ number_format($record->total_credit, 2) }}</th></tr></tbody></table>
        <div class="signature-grid"><div class="signature">{{ $branding['prepared_by_ar'] }} / {{ $branding['prepared_by_en'] }}<br>{{ $record->prepared_by_name }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div><div class="signature">{{ $branding['received_by_ar'] }} / {{ $branding['received_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
