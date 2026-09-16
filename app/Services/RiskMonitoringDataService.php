<?php

namespace App\Services;

use App\Models\PrenatalVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMonitoringDataService
{
    public function visits(Request $request): LengthAwarePaginator
    {
        $query = PrenatalVisit::with('patient.referrals')
            ->whereIn('id', $this->latestVisitSubquery());

        $query->whereHas('patient', function ($q) {
            $q->whereIn('status', ['ONGOING', 'DELIVERED', 'REFERRED']);
        });

        $riskFilter = $request->risk_filter;
        if ($riskFilter && in_array($riskFilter, ['HIGH', 'LOW', 'ASSESSMENT INCOMPLETE'], true)) {
            $query->where('risk_level', $riskFilter);
        } else {
            $query->orderByRaw("CASE WHEN risk_level = 'HIGH' THEN 0 WHEN risk_level = 'LOW' THEN 1 ELSE 2 END");
        }

        $decisionSource = $request->decision_source;
        if ($decisionSource && in_array($decisionSource, ['COMPLETENESS', 'RULE_BASED', 'MACHINE_LEARNING', 'MACHINE_LEARNING_INVALID'], true)) {
            $query->where('decision_source', $decisionSource);
        }

        $urgency = $request->urgency;
        if ($urgency && in_array($urgency, ['URGENT_CLINICAL_REVIEW', 'PROMPT'], true)) {
            $query->where('urgency', $urgency);
        }

        $verificationStatus = $request->bp_verification_status;
        if ($verificationStatus && in_array($verificationStatus, ['PENDING_REPEAT', 'REPEAT_COMPLETED', 'UNABLE_TO_REPEAT', 'NOT_REQUIRED'], true)) {
            $query->where('bp_verification_status', $verificationStatus);
        }

        if ($request->search) {
            $search = $request->search;
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $search . '%']);
            });
        }

        return $query->paginate(15);
    }

    public function monthFilter(mixed $value): ?int
    {
        if ($value === 'all' || $value === null || $value === '') {
            return null;
        }

        $month = (int) $value;

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    public function riskTypeFilter(mixed $value): string
    {
        return $value === 'LOW' ? 'LOW' : 'HIGH';
    }

    private function latestVisitSubquery(): Builder
    {
        return DB::table('prenatal_visits')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id) as id')
            ->groupBy('patient_id');
    }
}
