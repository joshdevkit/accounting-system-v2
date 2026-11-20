<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $company['name'] }}</title>
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

        /* Table */
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
            padding: 6px 8px;
        }
        table.data th.amount, table.data td.amount {
            text-align: right;
        }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #dde3e8;
        }
        table.data tr.summary td {
            font-weight: bold;
            background-color: #eef1f4;
        }
        table.data tr.totals td {
            font-weight: bold;
            color: #1c2b3a;
            border-top: 2px solid #1c2b3a;
            border-bottom: none;
            padding-top: 8px;
        }

        .indent-1 { padding-left: 18px; }
        .indent-2 { padding-left: 30px; }
        .indent-3 { padding-left: 42px; }

        .code {
            color: #6b7785;
            font-size: 9px;
        }
        .badge {
            display: inline-block;
            font-size: 7.5px;
            color: #6b7785;
            border: 1px solid #dde3e8;
            border-radius: 3px;
            padding: 1px 5px;
            margin-left: 4px;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 8.5px;
            color: #6b7785;
        }

        /* Page footer with page numbers */
        .page-footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
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
                    <p class="report-title">Trial Balance</p>
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

    <table class="data">
        <thead>
            <tr>
                <th>Account</th>
                <th class="amount">Debit</th>
                <th class="amount">Credit</th>
                <th class="amount">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ $row['is_summary'] ? 'summary' : '' }}">
                    <td class="indent-{{ $row['depth'] }}">
                        <span class="code">{{ $row['code'] }}</span>
                        {{ $row['name'] }}
                        @if($row['is_summary'])
                            <span class="badge">summary</span>
                        @endif
                    </td>
                    <td class="amount">{{ $row['debit'] ? '₱'.number_format($row['debit'] / 100, 2) : '—' }}</td>
                    <td class="amount">{{ $row['credit'] ? '₱'.number_format($row['credit'] / 100, 2) : '—' }}</td>
                    <td class="amount">₱{{ number_format($row['balance'] / 100, 2) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Totals</td>
                <td class="amount">₱{{ number_format($totals['debits'] / 100, 2) }}</td>
                <td class="amount">₱{{ number_format($totals['credits'] / 100, 2) }}</td>
                <td class="amount"></td>
            </tr>
        </tbody>
    </table>

    <p class="footer-note">
        Summary accounts roll up their sub-accounts, so only the leaf balances are counted once. Draft entries are excluded by design.
    </p>

    <div class="page-footer">
        {{ $company['name'] }} · Page <span class="pagenum"></span>
    </div>
</body>
</html>