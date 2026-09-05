<?php

use Illuminate\Support\Facades\Schema;

it('creates the approved obstetric-history columns in the patients schema', function () {
    expect(Schema::hasColumn('patients', 'previous_cs'))->toBeTrue();
    expect(Schema::hasColumn('patients', 'miscarriage'))->toBeTrue();
});

it('creates the approved recommendation column in the prenatal visits schema', function () {
    expect(Schema::hasColumn('prenatal_visits', 'recommendation'))->toBeTrue();
});

it('adds patients.previous_cs as a nullable boolean column', function () {
    $column = collect(Schema::getColumns('patients'))
        ->firstWhere('name', 'previous_cs');

    expect($column)->not->toBeNull();
    expect((bool) $column['nullable'])->toBeTrue();
});

it('adds patients.miscarriage as a nullable integer column', function () {
    $column = collect(Schema::getColumns('patients'))
        ->firstWhere('name', 'miscarriage');

    expect($column)->not->toBeNull();
    expect((bool) $column['nullable'])->toBeTrue();
});