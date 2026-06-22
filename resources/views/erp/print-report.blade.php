@php
    $rows = collect($rows ?? []);
    $first = (array) ($rows->first() ?? []);
    $moneyFields = ['grand_total', 'paid_total', 'balance_due', 'outstanding_amount', 'cost_value', 'retail_value', 'total_debit', 'total_credit'];
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $currency = config('erp.module.currency', 'SAR');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ __('print.report') }}</title>
    <style>
        @font-face {
            font-family: "DINNextLTArabic";
            src: url("/fonts/DINNextLTArabic-Regular-2.otf") format("opentype");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        * { box-sizing: border-box; letter-spacing: 0; }
        body {
            margin: 0;
            color: #122025;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }
        html[lang="ar"] body { font-family: "DINNextLTArabic", Tahoma, Arial, sans-serif; }
        .page { max-width: 1120px; margin: 0 auto; padding: 28px; }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 6px; font-size: 28px; }
        .muted { color: #64727b; }
        .table-wrap { max-width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #dfe7eb; padding: 8px; text-align: start; vertical-align: top; }
        th { background: #f4f8f8; text-transform: uppercase; font-size: 11px; color: #40515a; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 14px; }
        .button {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            border-radius: 8px;
            border: 1px solid #dfe7eb;
            background: #0f766e;
            color: #fff;
            padding: 0 12px;
            text-decoration: none;
            font-weight: 850;
        }
        @media print {
            .toolbar { display: none; }
            .page { padding: 0; }
            body { margin: 12mm; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="toolbar">
            <a class="button" href="{{ route('erp.reports.export', ['report' => $report, 'format' => 'csv', 'branch_id' => request('branch_id'), 'lang' => $locale]) }}">{{ __('print.download_csv') }}</a>
            @if(in_array($report, ['accounting-journals','trial-balance','general-ledger','profit-and-loss','balance-sheet'], true))
                <a class="button" href="{{ route('documents.financial-reports.show', ['report' => $report, 'branch_id' => request('branch_id'), 'date_from' => request('date_from'), 'date_to' => request('date_to'), 'account_id' => request('account_id'), 'lang' => $locale, 'format' => 'pdf']) }}">PDF</a>
            @elseif($report === 'inventory-valuation')
                <a class="button" href="{{ route('documents.stock-report', ['branch_id' => request('branch_id'), 'lang' => $locale, 'format' => 'pdf']) }}">PDF</a>
            @endif
            <button class="button" onclick="window.print()">{{ __('print.print_save_pdf') }}</button>
        </div>
        <header class="header">
            <div>
                <h1>{{ $title }}</h1>
                <div class="muted">{{ __('print.report') }} &middot; {{ $branch->name ?? __('print.all_branches') }}</div>
            </div>
            <div class="muted">
                {{ __('common.generated') }} {{ $generatedAt->format('Y-m-d H:i') }}<br>
                {{ __('common.report_code') }}: {{ $report }}
            </div>
        </header>

        @if($rows->isEmpty())
            <p class="muted">{{ __('print.no_data') }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach(array_keys($first) as $column)
                                @php($fieldKey = 'field.'.$column)
                                <th>{{ __($fieldKey) === $fieldKey ? str($column)->replace('_', ' ')->title() : __($fieldKey) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach((array) $row as $column => $value)
                                    <td>
                                        @if(in_array($column, $moneyFields, true) && is_numeric($value))
                                            {{ $currency }} {{ number_format((float) $value, 2) }}
                                        @else
                                            {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
