<?php

use App\Services\GestationalAgeCalculator;

uses(Tests\TestCase::class);

test('calculates elapsed days as decimal gestational age rounded to one place', function () {
    $calculator = new GestationalAgeCalculator;

    expect($calculator->calculate('2026-01-01', '2026-01-08'))->toBe(1.0);
    expect($calculator->calculate('2026-01-01', '2026-03-06'))->toBe(9.1);
});

test('does not calculate a gestational age when the reference date precedes LMP', function () {
    expect((new GestationalAgeCalculator)->calculate('2026-03-20', '2026-03-19'))->toBeNull();
});

test('does not calculate when a date is unavailable', function () {
    $calculator = new GestationalAgeCalculator;

    expect($calculator->calculate(null, '2026-03-19'))->toBeNull();
    expect($calculator->calculate('2026-01-01', null))->toBeNull();
    expect($calculator->calculate('2026-01-01', '2026-02-31'))->toBeNull();
});

test('returns calculated values outside the form range for the caller to reject', function () {
    $calculator = new GestationalAgeCalculator;

    expect($calculator->calculate('2026-09-11', '2026-10-01'))->toBe(2.9);
    expect($calculator->calculate('2025-12-04', '2026-10-01'))->toBe(43.0);
});
