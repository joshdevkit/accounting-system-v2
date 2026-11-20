<?php

namespace App\Support;

/**
 * Renders an amount in centavos as Philippine peso words, matching the
 * "Pesos ___ Only" line on a printed check voucher.
 */
class NumberToWords
{
    protected static array $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    protected static array $tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function pesos(int $cents): string
    {
        $pesos = intdiv($cents, 100);
        $centavos = $cents % 100;

        $words = $pesos > 0 ? self::convert($pesos).' Pesos' : 'Zero Pesos';

        if ($centavos > 0) {
            $words .= ' and '.self::convert($centavos).' Centavos';
        }

        return $words.' Only';
    }

    protected static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        if ($number < 20) {
            return self::$ones[$number];
        }

        if ($number < 100) {
            $ten = intdiv($number, 10);
            $one = $number % 10;

            return trim(self::$tens[$ten].($one > 0 ? '-'.self::$ones[$one] : ''));
        }

        if ($number < 1000) {
            $hundred = intdiv($number, 100);
            $rest = $number % 100;

            return trim(self::$ones[$hundred].' Hundred'.($rest > 0 ? ' '.self::convert($rest) : ''));
        }

        if ($number < 1_000_000) {
            $thousand = intdiv($number, 1000);
            $rest = $number % 1000;

            return trim(self::convert($thousand).' Thousand'.($rest > 0 ? ' '.self::convert($rest) : ''));
        }

        $million = intdiv($number, 1_000_000);
        $rest = $number % 1_000_000;

        return trim(self::convert($million).' Million'.($rest > 0 ? ' '.self::convert($rest) : ''));
    }
}