<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $company['name'] }} - Balance Sheet</title>
    <style>
        @page {
            margin: 30px 36px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #1a1a1a;
        }

        /* Header */
        .letterhead {
            width: 100%;
            border-bottom: 2px solid #1c2b3a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .letterhead table {
            width: 100%;
        }
        .letterhead td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .letterhead .logo img {
            height: 36px;
        }
        .letterhead .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #1c2b3a;
        }
        .letterhead .report-meta {
            text-align: right;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #1c2b3a;
            margin: 0 0 2px 0;
        }
        .report-subtitle {
            font-size: 9px;
            color: #6b7785;
            margin: 0;
        }

        /* Section labels */
        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #ffffff;
            background-color: #1c2b3a;
            padding: 5px 8px;
            margin-top: 14px;
        }
        .section-title:first-of-type {
            margin-top: 0;
        }

        /* Table */
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #dde3e8;
        }
        table.data td.amount {
            text-align: right;
        }
        table.data tr.total td {
            font-weight: bold;
            color: #1c2b3a;
            border-top: 1px solid #1c2b3a;
            border-bottom: none;
            background-color: #eef1f4;
        }
        table.data tr.grand-total td {
            font-weight: bold;
            color: #1c2b3a;
            border-top: 2px solid #1c2b3a;
            border-bottom: 2px solid #1c2b3a;
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .code {
            color: #6b7785;
            font-size: 9px;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 8.5px;
            color: #6b7785;
        }

        .unbalanced-warning {
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #b91c1c;
            background-color: #fef2f2;
            color: #b91c1c;
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="letterhead">
        <table>
            <tr>
                <td class="logo" style="width: 60px;">
                    <img src="{{ public_path('images/logo.png') }}">
                </td>
                <td>
                    <div class="company-name">{{ $company['name'] }}</div>
                </td>
                <td class="report-meta">
                    <p class="report-title">Balance Sheet</p>
                    <p class="report-subtitle">As of {{ \Carbon\Carbon::parse($asOf)->format('j M Y') }}</p>
                    <p class="report-subtitle">Generated {{ now()->format('j M Y, g:i A') }}</p>
                </td>
            </tr>
        </table>
    </div>

    @if(! $isBalanced)
        <div class="unbalanced-warning">
            Warning: this balance sheet does not balance. Assets do not equal Liabilities + Equity.
        </div>
    @endif

    <div class="section-title">Assets</div>
    <table class="data">
        <tbody>
            @forelse($assets as $row)
                <tr>
                    <td><span class="code">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="amount">₱{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No assets as of this date.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total Assets</td>
                <td class="amount">₱{{ number_format($totals['assets'] / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Liabilities</div>
    <table class="data">
        <tbody>
            @forelse($liabilities as $row)
                <tr>
                    <td><span class="code">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="amount">₱{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No liabilities as of this date.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total Liabilities</td>
                <td class="amount">₱{{ number_format($totals['liabilities'] / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Equity</div>
    <table class="data">
        <tbody>
            @forelse($equity as $row)
                <tr>
                    <td>
                        @if($row['code'])
                            <span class="code">{{ $row['code'] }}</span>
                        @endif
                        {{ $row['name'] }}
                    </td>
                    <td class="amount">₱{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No equity as of this date.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total Equity</td>
                <td class="amount">₱{{ number_format($totals['equity'] / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="data">
        <tr class="grand-total">
            <td>Total Liabilities + Equity</td>
            <td class="amount">₱{{ number_format(($totals['liabilities'] + $totals['equity']) / 100, 2) }}</td>
        </tr>
    </table>

    <p class="footer-note">
        A snapshot as of the selected date — every posted transaction up to that day is included.
        "Current earnings" under Equity is net income calculated live from all revenue and expenses to date.
    </p>
</body>
</html>