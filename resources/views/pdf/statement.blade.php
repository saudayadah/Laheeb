<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 11pt; color: #1b1510; }
        .header { text-align: center; margin-bottom: 14px; }
        .header h1 { font-size: 15pt; margin: 0 0 2px; }
        .header .sub { font-size: 12pt; font-weight: bold; }
        .meta { font-size: 9.5pt; color: #555; }
        table.stmt { width: 100%; border-collapse: collapse; }
        table.stmt th, table.stmt td { border: 1px solid #333; padding: 5px 7px; }
        table.stmt th { background: #f3ead9; font-weight: bold; }
        .num { text-align: left; direction: ltr; font-family: inherit; }
        .open { background: #faf6ee; font-weight: bold; }
        .close { background: #f3ead9; font-weight: bold; font-size: 12pt; }
        .credit { color: #0a6b35; }
        .red { color: #b3261e; }
        .footer { margin-top: 10px; font-size: 8.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $bakeryName }}</h1>
        <div class="sub">كشف حساب / Account Statement</div>
        <div class="meta">
            {{ $customer->name }} ({{ $customer->code }})
            @if ($customer->vat_number) — الرقم الضريبي: {{ $customer->vat_number }} @endif
            <br>
            من {{ $from }} إلى {{ $to }}
        </div>
    </div>

    <table class="stmt">
        <thead>
            <tr>
                <th width="14%">التاريخ</th>
                <th>البيان</th>
                <th width="15%">عليه</th>
                <th width="15%">له</th>
                <th width="16%">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            <tr class="open">
                <td colspan="4">رصيد افتتاحي</td>
                <td class="num">{{ number_format((float) $statement['opening'], 2) }}</td>
            </tr>
            @foreach ($statement['rows'] as $row)
                <tr>
                    <td class="num">{{ $row['date'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="num">{{ (float) $row['debit'] > 0 ? number_format((float) $row['debit'], 2) : '' }}</td>
                    <td class="num credit">{{ (float) $row['credit'] > 0 ? number_format((float) $row['credit'], 2) : '' }}</td>
                    <td class="num">{{ number_format((float) $row['balance'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="close">
                <td colspan="4">الرصيد النهائي المستحق</td>
                <td class="num {{ (float) $statement['closing'] > 0 ? 'red' : '' }}">
                    {{ number_format((float) $statement['closing'], 2) }} ر.س
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer">صدر آليًا من منصة {{ $bakeryName }} — {{ now()->format('Y-m-d H:i') }}</div>
</body>
</html>
