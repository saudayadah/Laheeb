<?php

use App\Support\Money;

test('line total rounds half-up once at line level', function () {
    // 1000 x 0.2750 = 275.00
    expect(Money::line(1000, '0.2750'))->toBe('275.00');

    // 333 x 0.2750 = 91.575 -> 91.58 (half-up)
    expect(Money::line(333, '0.2750'))->toBe('91.58');

    // 1 x 0.2749 = 0.2749 -> 0.27
    expect(Money::line(1, '0.2749'))->toBe('0.27');

    // 15 x 0.3350 = 5.025 -> 5.03 (half-up, where floats would give 5.02)
    expect(Money::line(15, '0.3350'))->toBe('5.03');
});

test('sum adds 2-decimal amounts exactly', function () {
    expect(Money::sum(['0.10', '0.20', '0.30']))->toBe('0.60');
    expect(Money::sum([]))->toBe('0.00');
});

test('vat is extracted correctly from inclusive amounts', function () {
    // 115.00 inclusive at 15% -> VAT = 15.00
    expect(Money::vatFromInclusive('115.00', '15'))->toBe('15.00');

    // 100.00 inclusive at 15% -> VAT = 13.04
    expect(Money::vatFromInclusive('100.00', '15'))->toBe('13.04');
});

test('vat is added correctly on exclusive amounts', function () {
    expect(Money::vatFromExclusive('100.00', '15'))->toBe('15.00');
    expect(Money::vatFromExclusive('0.30', '15'))->toBe('0.05');
});

test('compare and arithmetic helpers work', function () {
    expect(Money::add('10.10', '0.90'))->toBe('11.00');
    expect(Money::subtract('10.00', '10.00'))->toBe('0.00');
    expect(Money::isZero('0.00'))->toBeTrue();
    expect(Money::compare('1.01', '1.00'))->toBe(1);
});
