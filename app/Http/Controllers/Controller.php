<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function logAction($action, $module, $description)
{
    \App\Models\AuditLog::create([
        'user_id' => auth()->id() ?? 1, // ✅ fallback fix
        'action' => $action,
        'module' => $module,
        'description' => $description,
    ]);
}
}
