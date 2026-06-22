@extends('documents.base')
@section('document-title', $t('Glasses Card', 'كرت النظارة').' '.$record->order_number)
@section('document')
<main class="document-page glasses-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <div class="meta-grid" style="grid-template-columns:2fr 1fr 1fr;margin:2mm 0"><div class="meta-cell"><span class="meta-label">{{ $t('Name', 'الاسم') }}</span><span class="meta-value">{{ $record->patient_name }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->order_date }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Order No.', 'رقم الطلب') }}</span><span class="meta-value">{{ $record->order_number }}</span></div></div>
        @php($rx = $record->prescription)
        <table class="rx-table" style="margin:1mm 0"><thead><tr><th>{{ $t('Eye', 'العين') }}</th><th>SPH</th><th>CYL</th><th>AXIS</th><th>V.A.</th><th>ADD</th><th>PRISM</th><th>P.D.</th></tr></thead><tbody><tr><th>RIGHT</th><td>{{ $rx?->right_sph }}</td><td>{{ $rx?->right_cyl }}</td><td>{{ $rx?->right_axis }}</td><td>{{ $rx?->right_va }}</td><td>{{ $rx?->right_add }}</td><td></td><td>{{ $rx?->right_pd }}</td></tr><tr><th>LEFT</th><td>{{ $rx?->left_sph }}</td><td>{{ $rx?->left_cyl }}</td><td>{{ $rx?->left_axis }}</td><td>{{ $rx?->left_va }}</td><td>{{ $rx?->left_add }}</td><td></td><td>{{ $rx?->left_pd }}</td></tr></tbody></table>
        <div style="display:flex;justify-content:space-between;font-size:8pt"><span>{{ $t('Notes', 'ملاحظات') }}: {{ $record->fitting_notes }}</span><span>{{ $t('Optometrist', 'أخصائي البصريات') }}: {{ $rx?->optometrist_name }}</span></div>
    </div>
</main>
@endsection
