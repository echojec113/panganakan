<?php

use App\Support\WeightFormatter;

it('formats kilogram weights to one decimal place', function (int|float|string $weight, string $expected) {
    expect(WeightFormatter::formatKg($weight))->toBe($expected);
})->with([
    'whole number' => [60, '60.0'],
    'one decimal' => [60.5, '60.5'],
    'two decimal input with trailing zero' => ['60.90', '60.9'],
    'rounds half up' => ['60.25', '60.3'],
    'birth whole number' => ['3.00', '3.0'],
    'birth one decimal' => ['3.50', '3.5'],
    'birth rounds half up' => ['3.25', '3.3'],
]);

it('keeps missing kilogram weights empty', function () {
    expect(WeightFormatter::formatKg(null))->toBeNull()
        ->and(WeightFormatter::formatKg(''))->toBeNull();
});
