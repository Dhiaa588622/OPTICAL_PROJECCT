@extends('documents.base')
@section('document-title', $t('Optical Prescription', 'وصفة بصرية').' '.$record->prescription_number)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Optical Prescription', 'وصفة بصرية') }}</h1>
        <div class="meta-grid">
            <div class="meta-cell"><span class="meta-label">{{ $t('File No.', 'رقم الملف') }}</span><span class="meta-value">{{ $record->patient_code }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Patient', 'المريض') }}</span><span class="meta-value">{{ $record->full_name }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->prescribed_on }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Contact', 'رقم التواصل') }}</span><span class="meta-value">{{ $record->phone ?: $record->whatsapp_number }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Optometrist', 'أخصائي البصريات') }}</span><span class="meta-value">{{ $record->optometrist_name ?: '-' }}</span></div>
        </div>
        <div class="section-title">{{ $t('Prescribed RX', 'الوصفة الطبية') }}</div>
        <table class="rx-table"><thead><tr><th>{{ $t('Eye', 'العين') }}</th><th>SPH</th><th>CYL</th><th>AXIS</th><th>V.A.</th><th>ADD</th><th>P.D.</th></tr></thead><tbody><tr><th>RIGHT / OD</th><td>{{ $record->right_sph }}</td><td>{{ $record->right_cyl }}</td><td>{{ $record->right_axis }}</td><td>{{ $record->right_va }}</td><td>{{ $record->right_add }}</td><td>{{ $record->right_pd }}</td></tr><tr><th>LEFT / OS</th><td>{{ $record->left_sph }}</td><td>{{ $record->left_cyl }}</td><td>{{ $record->left_axis }}</td><td>{{ $record->left_va }}</td><td>{{ $record->left_add }}</td><td>{{ $record->left_pd }}</td></tr></tbody></table>
        <div class="section-title">{{ $t('Diagnosis', 'التشخيص') }}</div><div class="blank-area">{{ $record->diagnosis }}</div>
        <div class="section-title">{{ $t('Recommendation', 'التوصية') }}</div><div class="blank-area">{{ $record->recommendation }}</div>
        @if($record->notes)<div class="note-box"><strong>{{ $t('Notes', 'ملاحظات') }}:</strong> {{ $record->notes }}</div>@endif
        <div class="signature-grid"><div class="signature">{{ $t('Patient', 'المريض') }}</div><div class="signature">{{ $t('Optometrist', 'أخصائي البصريات') }}<br>{{ $record->optometrist_name }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
