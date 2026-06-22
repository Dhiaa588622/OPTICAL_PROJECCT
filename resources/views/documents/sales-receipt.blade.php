@extends('documents.base')
@section('document-title', $t('Receipt', 'إيصال').' '.$record->payment_number)
@section('document')
<main class="document-page thermal-page">
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Receipt', 'إيصال') }}</h1>
        <div class="doc-number">{{ $record->payment_number }} | {{ $record->paid_at }}</div>
        <p><strong>{{ $t('Customer', 'العميل') }}:</strong> {{ $record->customer_name ?: $t('Walk-in', 'نقدي') }}</p>
        @if($record->invoice_number)<p><strong>{{ $t('Invoice', 'الفاتورة') }}:</strong> {{ $record->invoice_number }}</p>@endif
        @if($record->items->isNotEmpty())<table><thead><tr><th>{{ $t('Item', 'الصنف') }}</th><th>{{ $t('Qty', 'الكمية') }}</th><th>{{ $t('Total', 'الإجمالي') }}</th></tr></thead><tbody>@foreach($record->items as $item)<tr><td>{{ $item->description }}</td><td>{{ number_format($item->quantity, 2) }}</td><td>{{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody></table>@endif
        <div class="totals"><div class="totals-row grand"><span>{{ $t('Paid', 'المدفوع') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->amount, 2) }}</strong></div><div class="totals-row"><span>{{ $t('Method', 'الطريقة') }}</span><strong>{{ str($record->payment_method)->replace('_', ' ')->title() }}</strong></div><div class="totals-row"><span>{{ $t('Balance', 'المتبقي') }}</span><strong>{{ $branding['currency'] }} {{ number_format($record->balance_due ?? 0, 2) }}</strong></div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
