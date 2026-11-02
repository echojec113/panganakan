<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function logAction(
        string $action,
        string $module,
        string $description,
        ?string $recordType = null,
        ?int $recordId = null,
        ?int $patientId = null
    ): void {
        $actor = auth()->user();

        \App\Models\AuditLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name ?? 'System',
            'patient_id' => $patientId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'record_type' => $recordType,
            'record_id' => $recordId,
        ]);
    }
}
