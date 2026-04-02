<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

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

        $query = AuditLog::with('user')->latest();

        // 🔍 FILTER: action
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // 🔍 FILTER: module
        if ($request->module) {
            $query->where('module', $request->module);
        }

        // 🔍 SEARCH
        if ($request->search) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate(10);

        return view('audit_logs.index', compact('logs'));
    }
}