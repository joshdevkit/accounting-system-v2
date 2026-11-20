<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: 8.5in 5.5in;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 8.5in;
            height: 5.5in;
        }

        body {
            font-family: "Georgia", "DejaVu Serif", serif;
            color: #000;
            font-size: 8px;
        }

        .voucher {
            position: relative;
            width: 8.5in;
            height: 5.5in;
            overflow: hidden;
        }


        /*
        |--------------------------------------------------------------------------
        | PAYEE & WINDOW BRACKETS
        |--------------------------------------------------------------------------
        */

        .payee-label {
            position: absolute;
            left: 0.65in;
            top: 0.68in;
            font-size: 8.5px;
            font-weight: bold;
        }

        .payee-box-left {
            position: absolute;
            left: 1.42in;
            top: 0.48in;
            width: 0.25in;
            height: 0.52in;
        }

        .payee-box-right {
            position: absolute;
            left: 3.32in;
            top: 0.48in;
            width: 0.25in;
            height: 0.52in;
        }

        .corner {
            position: absolute;
            width: 0.18in;
            height: 0.18in;
        }

        .corner-tl {
            top: 0;
            left: 0;
            border-top: 1.5px solid #000;
            border-left: 1.5px solid #000;
        }

        .corner-bl {
            bottom: 0;
            left: 0;
            border-bottom: 1.5px solid #000;
            border-left: 1.5px solid #000;
        }

        .corner-tr {
            top: 0;
            right: 0;
            border-top: 1.5px solid #000;
            border-right: 1.5px solid #000;
        }

        .corner-br {
            bottom: 0;
            right: 0;
            border-bottom: 1.5px solid #000;
            border-right: 1.5px solid #000;
        }

        .payee-name {
            position: absolute;
            left: 1.99in;
            top: 0.66in;
            font-size: 8.5px;
            font-weight: bold;
            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK VOUCHER TITLE
        |--------------------------------------------------------------------------
        */

        .voucher-title {
            position: absolute;
            right: 0.65in;
            top: 0.48in;
            width: 2.15in;
            text-align: left;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .voucher-meta {
            position: absolute;
            right: 0.65in;
            top: 0.74in;
            width: 2.15in;
            font-size: 8px;
        }

        .meta-row {
            height: 0.18in;
            line-height: 0.18in;
            white-space: nowrap;
        }

        .meta-line {
            display: inline-block;
            width: 1.65in;
            margin-left: 0.04in;
            border-bottom: 1px dotted #000;
            height: 0.14in;
            vertical-align: bottom;
            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | PARTICULARS (FIXED LARGE RECTANGLE & CENTERED TEXT)
        |--------------------------------------------------------------------------
        */

        .particulars {
            position: absolute;
            left: 0.48in;
            top: 1.22in;
            width: 7.54in;
            height: 1.45in; /* Enforces full rectangular height */
            border-collapse: collapse;
            table-layout: fixed;
        }

        .particulars th,
        .particulars td {
            border: 1px solid #000;
            padding: 0;
        }

        .particulars thead tr {
            height: 0.22in;
        }

        .particulars th {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-align: center;
            vertical-align: middle;
        }

        .particulars th:first-child {
            width: 4.80in;
        }

        .particulars th:last-child {
            width: 2.74in;
        }

        .particulars tbody tr {
            height: 1.23in; /* Forces the body row to fill the rectangle */
        }

        .particulars td {
            font-size: 8px;
            vertical-align: middle; /* Centers text vertically */
            height: 1.23in;
        }

        .particulars-text {
            text-align: center; /* Centers text horizontally */
            padding: 0.08in !important;
        }

        .particulars-amount {
            padding-right: 0.08in !important;
            text-align: right;
            vertical-align: middle;
            font-size: 8px;
        }


        /*
        |--------------------------------------------------------------------------
        | DISTRIBUTION AREA
        |--------------------------------------------------------------------------
        */

        .distribution-label {
            position: absolute;
            left: 0.48in;
            top: 2.82in;
            font-size: 8px;
            font-weight: bold;
        }

        .distribution {
            position: absolute;
            left: 0.48in;
            top: 2.96in;
            width: 3.82in;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .distribution th,
        .distribution td {
            border: 1px solid #000;
            padding: 0;
        }

        .distribution thead tr {
            height: 0.16in;
        }

        .distribution th {
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .distribution th:nth-child(1) {
            width: 1.82in;
        }

        .distribution th:nth-child(2) {
            width: 1.00in;
        }

        .distribution th:nth-child(3) {
            width: 1.00in;
        }

        .distribution tbody tr {
            height: 0.14in;
        }

        .distribution td {
            font-size: 7px;
            vertical-align: middle;
            padding-left: 0.04in;
            padding-right: 0.04in;
        }

        .distribution td.amount {
            text-align: right;
            padding-right: 0.05in;
        }


        /*
        |--------------------------------------------------------------------------
        | PESOS / BANK / PAYMENT SECTION
        |--------------------------------------------------------------------------
        */

        .payment-section {
            position: absolute;
            left: 4.38in;
            top: 2.96in;
            width: 3.64in;
            font-size: 8px;
        }

        .pesos-row {
            position: relative;
            height: 0.60in;
        }

        .pesos-label {
            position: absolute;
            left: 0;
            top: 0;
            font-weight: bold;
        }

        .pesos-content {
            position: absolute;
            left: 0.48in;
            top: -0.02in;
            width: 3.16in;
            font-size: 8px;
            line-height: 0.20in;
        }

        .pesos-line {
            position: absolute;
            left: 0.45in;
            width: 3.19in;
            border-bottom: 1px dotted #000;
        }

        .pesos-line-1 { top: 0.12in; }
        .pesos-line-2 { top: 0.32in; }
        .pesos-line-3 { top: 0.52in; }

        .bank-row {
            position: relative;
            margin-top: 0.08in;
            height: 0.22in;
            white-space: nowrap;
        }

        .bank-line {
            display: inline-block;
            width: 0.95in;
            height: 0.15in;
            margin-left: 0.04in;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
            text-align: center;
        }

        .check-label {
            margin-left: 0.12in;
        }

        .check-line {
            display: inline-block;
            width: 0.95in;
            height: 0.15in;
            margin-left: 0.04in;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
            text-align: center;
        }

        .received-row {
            position: relative;
            margin-top: 0.08in;
            height: 0.43in;
        }

        .received-line {
            position: absolute;
            left: 0;
            top: 0.28in;
            width: 3.64in;
            border-bottom: 1px dotted #000;
        }


        /*
        |--------------------------------------------------------------------------
        | SIGNATURES
        |--------------------------------------------------------------------------
        */

        .signatures {
            position: absolute;
            left: 0.48in;
            top: 4.25in;
            width: 7.54in;
        }

        .signatures td {
            border: none;
            padding: 0;
            font-size: 8px;
            text-align: center;
            vertical-align: top;
            width: 33.33%;
        }

        .signature-title {
            height: 0.35in;
            font-weight: bold;
            text-align: center;
        }

        .signature-line {
            width: 1.95in;
            margin: 0 auto;
            border-bottom: 1px dotted #000;
        }
    </style>
</head>

<body>

    <div class="voucher">

        {{-- PAYEE --}}
        <div class="payee-label">
            PAYEE
        </div>

        <div class="payee-box-left">
            <div class="corner corner-tl"></div>
            <div class="corner corner-bl"></div>
        </div>

        <div class="payee-name">
            {{ $payee }}
        </div>

        <div class="payee-box-right">
            <div class="corner corner-tr"></div>
            <div class="corner corner-br"></div>
        </div>


        {{-- TITLE / META --}}
        <div class="voucher-title">
            CHECK VOUCHER
        </div>

        <div class="voucher-meta">
            <div class="meta-row">
                No.
                <span class="meta-line">
                    {{ $voucher_no ?? '' }}
                </span>
            </div>

            <div class="meta-row">
                Date
                <span class="meta-line">
                    {{ \Carbon\Carbon::parse($date)->format('j M Y') }}
                </span>
            </div>
        </div>


        {{-- PARTICULARS --}}
        <table class="particulars">
            <thead>
                <tr>
                    <th>PARTICULARS</th>
                    <th>AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="particulars-text">
                        {{ $particulars }}
                    </td>
                    <td class="particulars-amount">
                        ₱{{ number_format($amount_cents / 100, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>


        {{-- DISTRIBUTION --}}
        <div class="distribution-label">
            Distribution of Account:
        </div>

        <table class="distribution">
            <thead>
                <tr>
                    <th>ACCOUNT TITLE</th>
                    <th>DEBIT</th>
                    <th>CREDIT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>
                            {{ $line['account_name'] }}
                        </td>
                        <td class="amount">
                            @if ($line['debit_cents'])
                                {{ number_format($line['debit_cents'] / 100, 2) }}
                            @endif
                        </td>
                        <td class="amount">
                            @if ($line['credit_cents'])
                                {{ number_format($line['credit_cents'] / 100, 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach

                @for ($i = count($lines); $i < 6; $i++)
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>


        {{-- PAYMENT DETAILS --}}
        <div class="payment-section">
            <div class="pesos-row">
                <span class="pesos-label">PESOS</span>
                <span class="pesos-content">{{ $amount_words }}</span>
                <div class="pesos-line pesos-line-1"></div>
                <div class="pesos-line pesos-line-2"></div>
                <div class="pesos-line pesos-line-3"></div>
            </div>

            <div class="bank-row">
                Bank
                <span class="bank-line">
                    {{ $bank_name ?? '' }}
                </span>
                <span class="check-label">
                    Check No.
                </span>
                <span class="check-line">
                    {{ $check_number ?? '' }}
                </span>
            </div>

            <div class="received-row">
                <strong>Received Payment By:</strong>
                <div class="received-line"></div>
            </div>
        </div>


        {{-- SIGNATURES --}}
        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">Prepared By:</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Certified Correct By:</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Approved By:</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>

    </div>

</body>

</html>