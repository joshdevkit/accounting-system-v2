<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: 8.5in 5in;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 8.5in;
            height: 5in;
            margin: 0;
            padding: 0;
        }

        body {
            color: #111;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
        }

        .field {
            position: absolute;
            white-space: nowrap;
        }

        .payee {
            left: 3cm;
            top: 3.5cm;
            max-width: 9cm;
            overflow: hidden;
            font-weight: bold;
        }

        .date {
            top: 4.2cm;
            right: 1.5cm;
        }

        .particulars {
            top: 6cm;
            left: 2cm;
            width: 14cm;
            height: 1.5cm;
            overflow: hidden;
            white-space: normal;
            line-height: 0.45cm;
        }

        .amount {
            top: 6cm;
            right: 1.5cm;
            text-align: right;
        }

        .total {
            right: 1.5cm;
            bottom: 3cm;
            text-align: right;
            font-weight: bold;
        }

        .words {
            top: 10cm;
            right: 1.5cm;
            width: 7.2cm;
            max-height: 1.35cm;
            overflow: hidden;
            text-align: left;
            white-space: normal;
            font-size: 6pt;
            line-height: 0.42cm;
        }

        .check-number {
            right: 1.5cm;
            bottom: 2.1cm;
        }

        .account-summary {
            position: absolute;
            top: 10cm;
            left: 1.3cm;
            width: 11.5cm;
            font-size: 7pt;
        }

        .account-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            line-height: 0.42cm;
            white-space: normal;
        }

        .account-name,
        .debit,
        .credit {
            display: table-cell;
            overflow: hidden;
        }

        .account-name {
            width: 6.7cm;
            white-space: nowrap;
            font-size: 6pt;
        }

        .debit,
        .credit {
            width: 2.4cm;
            text-align: right;
            white-space: nowrap;
            vertical-align: top;
        }
    </style>
</head>

<body>
    <div class="field payee">{{ $payee }}</div>
    <div class="field date">{{ \Carbon\Carbon::parse($date)->format('m/d/Y') }}</div>
    <div class="field particulars">{{ $particulars }}</div>
    <div class="field amount">{{ number_format($amount_cents / 100, 2) }}</div>
    <div class="field total">{{ number_format($amount_cents / 100, 2) }}</div>
    <div class="field words">{{ $amount_words }}</div>
    <div class="field check-number">{{ $check_number }}</div>

    <div class="account-summary">
        @foreach ($accounts as $account)
            <div class="account-row">
                <span class="account-name">{{ $account['name'] }}</span>
                <span class="debit">{{ $account['debit_cents'] ? number_format($account['debit_cents'] / 100, 2) : '' }}</span>
                <span class="credit">{{ $account['credit_cents'] ? number_format($account['credit_cents'] / 100, 2) : '' }}</span>
            </div>
        @endforeach
    </div>
</body>

</html>
