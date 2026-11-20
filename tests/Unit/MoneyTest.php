<?php

use App\Support\Money;

it('reads every amount as an exact number of centavos', function () {
    expect(Money::cents(1234.56))->toBe(123456)
        ->and(Money::cents('1234.56'))->toBe(123456)
        ->and(Money::cents('1234.567'))->toBe(123457)
        ->and(Money::cents(1234))->toBe(123400)
        ->and(Money::cents('0.1'))->toBe(10)
        ->and(Money::cents(''))->toBe(0)
        ->and(Money::cents(null))->toBe(0)
        ->and(Money::cents('not a number'))->toBe(0)
        ->and(Money::cents(' 45.5 '))->toBe(4550);
});

it('rounds half a centavo up instead of truncating', function () {
    // 0.005 is the classic float trap: it must not vanish or double count.
    expect(Money::cents(0.005))->toBe(1)
        ->and(Money::cents(1.005))->toBe(101)
        ->and(Money::cents(-0.005))->toBe(-1);
});

it('writes amounts back out for a decimal column', function () {
    expect(Money::decimal(123456))->toBe('1234.56')
        ->and(Money::decimal(0))->toBe('0.00')
        ->and(Money::decimal(10))->toBe('0.10')
        ->and(Money::decimal(-5))->toBe('-0.05');
});

it('adds amounts in whatever shape they arrive', function () {
    expect(Money::sum(['10.10', 10.10, null, '', 5]))->toBe(2520)
        ->and(Money::sum([]))->toBe(0);
});

it('formats centavos for a report', function () {
    expect(Money::format(123456))->toBe('1,234.56')
        ->and(Money::format(0))->toBe('0.00')
        ->and(Money::format(-4550))->toBe('-45.50');
});

it('never drifts when a centavo amount is added up a thousand times', function () {
    $cents = array_fill(0, 1000, '0.01');

    expect(Money::sum($cents))->toBe(1000)
        ->and(Money::decimal(Money::sum($cents)))->toBe('10.00');
});
