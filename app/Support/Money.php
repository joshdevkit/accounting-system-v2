<?php

namespace App\Support;

final class Money
{
    /**
     * Convert an amount in pesos into an integer number of centavos.
     *
     * All balancing maths in the application is done on integers so that
     * repeated float additions can never drift by a centavo.
     */
    public static function cents(int|float|string|null $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        if (is_string($amount)) {
            $amount = trim($amount);

            if ($amount === '' || ! is_numeric($amount)) {
                return 0;
            }
        }

        // Two steps: snapping to six decimals first means a float that lands a
        // hair under a half centavo (1.005 * 100 === 100.4999...) still rounds
        // the way an accountant expects, while two-decimal input is untouched.
        return (int) round(round(((float) $amount) * 100, 6));
    }

    /**
     * Format centavos as a fixed two-decimal string suitable for a decimal:2 column.
     */
    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * @param  iterable<int|string, int|float|string|null>  $amounts
     */
    public static function sum(iterable $amounts): int
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += self::cents($amount);
        }

        return $total;
    }

    /**
     * Render centavos as a grouped amount, e.g. 123456 -> "1,234.56".
     */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
