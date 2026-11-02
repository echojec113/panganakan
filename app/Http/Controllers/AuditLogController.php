<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    private function checkAdmin()
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $actions = collect([
            'CREATE',
            'UPDATE',
            'DELETE',
            'ARCHIVE',
            'RESTORE',
            'CONFIRM',
            'DECLINE',
            'CANCEL',
            'COMPLETE',
            'RESCHEDULE',
        ])->merge(AuditLog::query()->distinct()->orderBy('action')->pluck('action'))
            ->unique()
            ->sort()
            ->values();
        $modules = AuditLog::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $request->validate([
            'action' => ['nullable', Rule::in($actions->all())],
            'module' => ['nullable', Rule::in($modules->all())],
        ]);

        $query = AuditLog::with('user')->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (! empty($validated['search'])) {
            $query->where('description', 'like', '%' . $validated['search'] . '%');
        }

        $logs = $query->paginate(10)->withQueryString();

        return view('audit_logs.index', compact('logs', 'actions', 'modules'));
    }
}