<?php

use App\Models\Patient;
use App\Models\Ultrasound;
use App\ValueObjects\UltrasoundSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('fromModel captures the exact id, date, and evaluated values', function () {
    $patient = Patient::create([
        'first_name' => 'Jane', 'last_name' => 'Doe', 'age' => 28,
        'gravida' => 2, 'para' => 1, 'status' => 'ONGOING',
    ]);
    $model = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => '2026-08-01',
        'presentation' => 'BREECH',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Normal',
    ]);

    $snapshot = UltrasoundSnapshot::fromModel($model);

    expect($snapshot)->not->toBeNull();
    expect($snapshot->id)->toBe((int) $model->id);
    expect($snapshot->date)->toBe('2026-08-01');
    expect($snapshot->presentation)->toBe('BREECH');
    expect($snapshot->amniotic_fluid)->toBe('Low');
    expect($snapshot->fetal_heartbeat)->toBe('Normal');
});

test('fromModel returns null for a missing record', function () {
    expect(UltrasoundSnapshot::fromModel(null))->toBeNull();
});

test('inputs exposes only the three clinical findings, no model and no pii', function () {
    $snapshot = new UltrasoundSnapshot(
        id: 3,
        date: '2026-08-01',
        presentation: 'Cephalic',
        amniotic_fluid: 'Normal',
        fetal_heartbeat: 'Normal',
    );

    $inputs = $snapshot->inputs();

    expect($inputs)->toBe([
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);

    expect($inputs)->not->toHaveKey('id');
    expect($inputs)->not->toHaveKey('date');
});

test('toArray is a plain array with approved scalar values only', function () {
    $snapshot = new UltrasoundSnapshot(
        id: 3,
        date: '2026-08-01',
        presentation: 'Cephalic',
        amniotic_fluid: 'Normal',
        fetal_heartbeat: 'Normal',
    );

    expect($snapshot->toArray())->toBe([
        'id' => 3,
        'date' => '2026-08-01',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
});

test('values are trimmed but preserve case', function () {
    $snapshot = new UltrasoundSnapshot(null, null, '  Breech  ', '  LOW ', 'Absent ');

    expect($snapshot->presentation)->toBe('Breech');
    expect($snapshot->amniotic_fluid)->toBe('LOW');
    expect($snapshot->fetal_heartbeat)->toBe('Absent');
});
