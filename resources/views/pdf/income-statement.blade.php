<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $company['name'] }} - Income Statement</title>
    <style>
        @page {
            margin: 30px 36px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #1a1a1a;
        }

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

        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            text-align: left;
            font-size: 8.5px;
            font-weight: bold;
            color: #6b7785;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #1c2b3a;
            padding: 5px 8px;
        }
        table.data th.amount, table.data td.amount {
            text-align: right;
        }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #dde3e8;
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
            font-size: 12px;
        }

        .code {
            color: #6b7785;
            font-size: 9px;
        }
        .muted {
            color: #6b7785;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 8.5px;
            color: #6b7785;
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
                    <p class="report-title">Income Statement</p>
                    <p class="report-subtitle">
                        @if($filters['from'] ?? null)
                            {{ \Carbon\Carbon::parse($filters['from'])->format('j M Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('j M Y') }}
                        @else
                            All posted entries
                        @endif
                    </p>
                    <p class="report-subtitle">Generated {{ now()->format('j M Y, g:i A') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Revenue</div>
    <table class="data">
        <thead>
            <tr>
                <th>Account</th>
                <th class="amount">Amount</th>
                <th class="amount">% of Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse($revenue as $row)
                <tr>
                    <td><span class="code">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="amount">₱{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                    <td class="amount muted">{{ $totals['revenue'] > 0 ? number_format($row['amount_cents'] / $totals['revenue'] * 100, 1) : '0.0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="3">No revenue posted for this range.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total Revenue</td>
                <td class="amount">₱{{ number_format($totals['revenue'] / 100, 2) }}</td>
                <td class="amount">100.0%</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Expenses</div>
    <table class="data">
        <thead>
            <tr>
                <th>Account</th>
                <th class="amount">Amount</th>
                <th class="amount">% of Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $row)
                <tr>
                    <td><span class="code">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                    <td class="amount">₱{{ number_format($row['amount_cents'] / 100, 2) }}</td>
                    <td class="amount muted">{{ $totals['revenue'] > 0 ? number_format($row['amount_cents'] / $totals['revenue'] * 100, 1) : '0.0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="3">No expenses posted for this range.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total Expenses</td>
                <td class="amount">₱{{ number_format($totals['expenses'] / 100, 2) }}</td>
                <td class="amount">{{ $totals['revenue'] > 0 ? number_format($totals['expenses'] / $totals['revenue'] * 100, 1) : '0.0' }}%</td>
            </tr>
        </tbody>
    </table>

    <table class="data">
        <tr class="grand-total">
            <td>{{ $totals['net_income'] >= 0 ? 'Net Income' : 'Net Loss' }}</td>
            <td class="amount" colspan="2">₱{{ number_format($totals['net_income'] / 100, 2) }}</td>
        </tr>
    </table>

    <p class="footer-note">
        Only revenue and expense accounts appear here. Draft entries are excluded by design.
    </p>
</body>
</html>