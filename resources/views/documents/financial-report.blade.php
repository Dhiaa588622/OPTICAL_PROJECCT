@extends('documents.base')
@section('document-title', $title)
@section('document')
<main class="document-page compact">
    @include('documents.partials.watermark')
    <div class="document-content">
        @include('documents.partials.header')
        <h1 class="doc-title">{{ $title }}</h1>
        <div class="doc-number">{{ $t('Generated', 'تاريخ الإصدار') }}: {{ now()->format('Y-m-d H:i') }} @if(request('date_from')) | {{ request('date_from') }} - {{ request('date_to') }} @endif</div>
        @if($rows->isEmpty())<div class="note-box">{{ $t('No data for the selected period.', 'لا توجد بيانات للفترة المحددة.') }}</div>@else
            @php($columns = array_keys((array) $rows->first()))
            <table><thead><tr>@foreach($columns as $column)<th>{{ str($column)->replace('_', ' ')->title() }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $row)<tr>@foreach($columns as $column)@php($value = data_get($row, $column))<td class="{{ is_numeric($value) ? 'numeric' : '' }}">{{ is_numeric($value) ? number_format((float)$value, 2) : (is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) }}</td>@endforeach</tr>@endforeach</tbody></table>
        @endif
        <div class="signature-grid"><div class="signature">{{ $t('Prepared by', 'إعداد') }}</div><div class="signature">{{ $t('Accountant', 'المحاسب') }}</div><div class="signature">{{ $branding['approved_by_ar'] }} / {{ $branding['approved_by_en'] }}</div></div>
        @include('documents.partials.footer')
    </div>
</main>
@endsection
