<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    public function predict(Patient $patient, array $inputs): array
    {
        $mlInputs = $this->buildFeatureArray($patient, $inputs);

        Log::info('ML FEATURE ARRAY: ' . json_encode($mlInputs) . ' | Patient ID: ' . $patient->id);

        $python = $this->resolvePython();
        $script = escapeshellarg(base_path('maternal-risk-ml/predict.py'));
        $inputsStr = implode(' ', array_map('escapeshellarg', $mlInputs));
        $command = "{$python} {$script} {$inputsStr} 2>&1";

        Log::info('ML COMMAND: ' . $command . ' | Patient ID: ' . $patient->id);

        $output = shell_exec($command);
        $rawMlOutput = trim((string) $output);

        $outputLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawMlOutput)));
        $parsedMlOutput = $outputLines ? end($outputLines) : '';

        Log::info('ML RAW OUTPUT: ' . $rawMlOutput . ' | PARSED OUTPUT: ' . $parsedMlOutput . ' | Patient ID: ' . $patient->id);

        return $this->makeResult($rawMlOutput, $parsedMlOutput);
    }

    public function buildFeatureArray(Patient $patient, array $inputs): array
    {
        return [
            (float) ($patient->age ?? 0),
            (float) ($patient->gravida ?? 0),
            (float) ($patient->para ?? 0),
            (float) ($inputs['bp_sys'] ?? 0),
            (float) ($inputs['bp_dia'] ?? 0),
            (float) ($inputs['weight'] ?? 0),
            (float) ($inputs['gestational_age'] ?? 0),
            (int) ($inputs['hypertension'] ?? 0),
            (int) ($inputs['diabetes'] ?? 0),
            (int) ($patient->previous_cs ?? 0),
            (int) ($patient->miscarriage ?? 0),
            (int) ($inputs['anemia'] ?? 0)
        ];
    }

    public function makeResult(string $rawOutput, string $parsedOutput): array
    {
        $valid = false;
        $prediction = null;

        if ($parsedOutput !== '' && !preg_match('/error|exception|traceback|failed|unable/i', $rawOutput)) {
            $normalized = strtoupper($parsedOutput);
            if (in_array($normalized, ['LOW', 'HIGH'], true)) {
                $prediction = $normalized;
                $valid = true;
            }
        }

        return [
            'valid' => $valid,
            'prediction' => $prediction,
            'raw_output' => $rawOutput,
            'parsed_output' => $parsedOutput,
        ];
    }

    private function resolvePython(): string
    {
        $configuredPath = trim(env('PYTHON_PATH', ''));
        if ($configuredPath !== '' && file_exists($configuredPath)) {
            return escapeshellarg($configuredPath);
        }
        if ($configuredPath !== '') {
            Log::warning('Configured PYTHON_PATH does not exist, falling back to python on PATH: ' . $configuredPath);
        }
        return 'python';
    }
}
