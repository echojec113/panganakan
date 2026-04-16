<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Patient;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Show all referrals
     */
    public function index()
    {
        $query = Referral::with('patient')->latest();

        // Search by patient name
        if (request('search')) {
            $search = request('search');
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%");
            });
        }

        // Filter by status
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        $referrals = $query->paginate(15);

        // Stats
        $total = Referral::count();
        $pending = Referral::where('status', 'Pending')->count();
        $completed = Referral::where('status', 'Completed')->count();

        return view('referrals.index', compact('referrals', 'total', 'pending', 'completed'));
    }

    /**
     * Show create form
     */
    public function create($id)
    {
        $patient = Patient::findOrFail($id);

        // Block delivered patients
        if ($patient->status === 'DELIVERED') {
            return redirect()->back()
                ->with('error', 'Delivered patients cannot be referred.');
        }

        return view('referrals.create', compact('patient'));
    }

    /**
     * Store new referral
     */
    public function store(Request $request)
    {
        $request->validate([
            'patient_id'    => 'required|exists:patients,id',
            'referred_to'   => 'required|string|max:255',
            'doctor_name'   => 'nullable|string|max:255',
            'reason'        => 'required|string',
            'notes'         => 'nullable|string',
            'date_referred' => 'required|date',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        $referral = Referral::create([
            'patient_id'    => $request->patient_id,
            'created_by'    => auth()->id(),
            'referred_to'   => $request->referred_to,
            'doctor_name'   => $request->doctor_name,
            'reason'        => $request->reason,
            'notes'         => $request->notes,
            'referral_date' => $request->date_referred,
            'status'        => 'Pending',
        ]);

        // Audit log
        $this->logAction(
            'CREATE',
            'REFERRAL',
            'Created referral for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        return redirect()
            ->route('referrals.index')
            ->with('success', 'Referral created successfully.');
    }

    /**
     * Mark referral as completed
     */
    public function complete($id)
    {
        $referral = Referral::with('patient')->findOrFail($id);

        $referral->update([
            'status'       => 'Completed',
            'completed_at' => now(),
        ]);

        // Audit log
        $this->logAction(
            'UPDATE',
            'REFERRAL',
            'Completed referral for patient: ' . $referral->patient->first_name . ' ' . $referral->patient->last_name
        );

        return redirect()->back()
            ->with('success', 'Referral marked as completed.');
    }

    /**
     * Print referral letter
     */
    public function print($id)
    {
        $referral = Referral::with('patient', 'user')->findOrFail($id);

        return view('referrals.print', compact('referral'));
    }
}
