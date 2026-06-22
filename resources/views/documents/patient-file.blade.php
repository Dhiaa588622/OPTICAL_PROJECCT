@extends('documents.base')
@section('document-title', $t('Patient File', 'ملف المريض').' '.$record->patient_code)
@section('document')
<main class="document-page">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Patient File', 'ملف المريض') }}</h1>
        <div class="meta-grid">
            <div class="meta-cell"><span class="meta-label">{{ $t('File No.', 'رقم الملف') }}</span><span class="meta-value">{{ $record->patient_code }}</span></div>
            <div class="meta-cell wide"><span class="meta-label">{{ $t('Name', 'الاسم') }}</span><span class="meta-value">{{ $record->full_name }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Date of birth', 'تاريخ الميلاد') }}</span><span class="meta-value">{{ $record->date_of_birth ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Phone', 'الهاتف') }}</span><span class="meta-value">{{ $record->phone ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('WhatsApp', 'واتساب') }}</span><span class="meta-value">{{ $record->whatsapp_number ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Email', 'البريد') }}</span><span class="meta-value">{{ $record->email ?: '-' }}</span></div>
            <div class="meta-cell"><span class="meta-label">{{ $t('Gender', 'الجنس') }}</span><span class="meta-value">{{ str($record->gender)->replace('_', ' ')->title() }}</span></div>
        </div>
        <div class="section-title">{{ $t('Medical / Ocular History', 'التاريخ الطبي والبصري') }}</div><div class="blank-area">{{ $record->notes }}</div>
        @if($record->latest_prescription)
            <div class="section-title">{{ $t('Latest Prescribed RX', 'آخر وصفة بصرية') }} - {{ $record->latest_prescription->prescribed_on }}</div>
            <table class="rx-table"><thead><tr><th>{{ $t('Eye', 'العين') }}</th><th>SPH</th><th>CYL</th><th>AXIS</th><th>V.A.</th><th>ADD</th><th>P.D.</th></tr></thead><tbody><tr><th>RIGHT / OD</th><td>{{ $record->latest_prescription->right_sph }}</td><td>{{ $record->latest_prescription->right_cyl }}</td><td>{{ $record->latest_prescription->right_axis }}</td><td>{{ $record->latest_prescription->right_va }}</td><td>{{ $record->latest_prescription->right_add }}</td><td>{{ $record->latest_prescription->right_pd }}</td></tr><tr><th>LEFT / OS</th><td>{{ $record->latest_prescription->left_sph }}</td><td>{{ $record->latest_prescription->left_cyl }}</td><td>{{ $record->latest_prescription->left_axis }}</td><td>{{ $record->latest_prescription->left_va }}</td><td>{{ $record->latest_prescription->left_add }}</td><td>{{ $record->latest_prescription->left_pd }}</td></tr></tbody></table>
            <div class="meta-grid"><div class="meta-cell wide"><span class="meta-label">{{ $t('Diagnosis', 'التشخيص') }}</span><span class="meta-value">{{ $record->latest_prescription->diagnosis }}</span></div><div class="meta-cell wide"><span class="meta-label">{{ $t('Plan', 'الخطة') }}</span><span class="meta-value">{{ $record->latest_prescription->recommendation }}</span></div></div>
        @endif
        @include('documents.partials.footer')
    </div>
</main>
@if($record->latest_exam)
<main class="document-page page-break">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $t('Optometry Examination', 'فحص البصريات') }}</h1>
        <div class="meta-grid"><div class="meta-cell"><span class="meta-label">{{ $t('Exam No.', 'رقم الفحص') }}</span><span class="meta-value">{{ $record->latest_exam->exam_number }}</span></div><div class="meta-cell"><span class="meta-label">{{ $t('Date', 'التاريخ') }}</span><span class="meta-value">{{ $record->latest_exam->exam_date }}</span></div><div class="meta-cell wide"><span class="meta-label">{{ $t('Optometrist', 'أخصائي البصريات') }}</span><span class="meta-value">{{ $record->latest_exam->optometrist_name }}</span></div></div>
        <table class="rx-table"><thead><tr><th>{{ $t('Eye', 'العين') }}</th><th>SPH</th><th>CYL</th><th>AXIS</th><th>V.A.</th><th>ADD</th><th>P.D.</th></tr></thead><tbody><tr><th>RIGHT / OD</th><td>{{ $record->latest_exam->right_sph }}</td><td>{{ $record->latest_exam->right_cyl }}</td><td>{{ $record->latest_exam->right_axis }}</td><td>{{ $record->latest_exam->right_va }}</td><td>{{ $record->latest_exam->right_add }}</td><td>{{ $record->latest_exam->right_pd }}</td></tr><tr><th>LEFT / OS</th><td>{{ $record->latest_exam->left_sph }}</td><td>{{ $record->latest_exam->left_cyl }}</td><td>{{ $record->latest_exam->left_axis }}</td><td>{{ $record->latest_exam->left_va }}</td><td>{{ $record->latest_exam->left_add }}</td><td>{{ $record->latest_exam->left_pd }}</td></tr></tbody></table>
        <div class="section-title">{{ $t('Diagnosis', 'التشخيص') }}</div><div class="blank-area">{{ $record->latest_exam->diagnosis }}</div>
        <div class="section-title">{{ $t('Recommendation / Plan', 'التوصية / الخطة') }}</div><div class="blank-area">{{ $record->latest_exam->recommendation }}</div>
        <div class="section-title">{{ $t('Optometrist notes', 'ملاحظات أخصائي البصريات') }}</div><div class="blank-area">{{ $record->latest_exam->optometrist_notes }}</div>
        <div class="signature-grid"><div class="signature">{{ $t('Patient', 'المريض') }}</div><div class="signature">{{ $t('Optometrist', 'أخصائي البصريات') }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endif
@endsection
