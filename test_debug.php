<?php

require_once 'vendor/autoload.php';

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\PregnancyOutcomeVocabulary;
use Carbon\Carbon;

$service = new PregnancyOutcomeMonitoringService();
$asOf = Carbon::parse('2026-08-09 12:00:00');

// Clear any existing patients
Patient::truncate();

// Patient 1: ONGOING with edd 2026-08-01, no outcome
$p1 = Patient::create([
    'first_name' => 'Maria',
    'last_name' => 'Reyes1',
    'birthdate' => '1996-05-01',
    'age' => 30,
    'address' => 'Test address',
    'contact_number' => '09171234567',
    'gravida' => 2,
    'para' => 1,
    'status' => 'ONGOING',
    'edd' => '2026-08-01',
]);
echo 'P1 state: ' . $service->deriveState($p1, $asOf) . PHP_EOL;

// Patient 2: ONGOING with edd 2026-08-20, no outcome  
$p2 = Patient::create([
    'first_name' => 'Maria',
    'last_name' => 'Reyes2',
    'birthdate' => '1996-05-01',
    'age' => 30,
    'address' => 'Test address',
    'gravida' => 2,
    'para' => 1,
    'status' => 'ONGOING',
    'edd' => '2026-08-20',
]);
echo 'P2 state: ' . $service->deriveState($p2, $asOf) . PHP_EOL;

// Patient 3: DELIVERED with confirmed outcome
$p3 = Patient::create([
    'first_name' => 'Maria',
    'last_name' => 'Reyes3',
    'birthdate' => '1996-05-01',
    'age' => 30,
    'address' => 'Test address',
    'gravida' => 2,
    'para' => 1,
    'status' => 'DELIVERED',
    'delivery_date' => '2026-08-01',
    'edd' => '2026-08-15',
]);
$outcome3 = PregnancyOutcome::create([
    'patient_id' => $p3->id,
    'outcome_type' => 'DELIVERED',
    'confirmed_at' => Carbon::parse('2026-08-06'),
    'confirmation_source' => 'CLINIC_RECORD',
]);
echo 'P3 state: ' . $service->deriveState($p3, $asOf) . PHP_EOL;

// Patient 4: REFERRED
$p4 = Patient::create([
    'first_name' => 'Maria',
    'last_name' => 'Reyes4',
    'birthdate' => '1996-05-01',
    'age' => 30,
    'address' => 'Test address',
    'gravida' => 2,
    'para' => 1,
    'status' => 'REFERRED',
]);
echo 'P4 state: ' . $service->deriveState($p4, $asOf) . PHP_EOL;

// Count all patients
$all = Patient::with('pregnancyOutcome')->get();
echo PHP_EOL . '=== All patients ===' . PHP_EOL;
foreach ($all as $p) {
    echo 'Patient ' . $p->id . ' status=' . $p->status . ' edd=' . $p->edd . ' state=' . $service->deriveState($p, $asOf) . PHP_EOL;
}
echo PHP_EOL . '=== Counts ===' . PHP_EOL;
$counts = $service->countByState(Patient::with('pregnancyOutcome')->get());
foreach ($counts as $state => $count) {
    echo $state . ': ' . $count . PHP_EOL;
}