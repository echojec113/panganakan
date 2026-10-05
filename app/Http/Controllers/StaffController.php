<?php

namespace App\Http\Controllers;

use App\Mail\StaffCredentialMail;
use App\Models\User;
use App\Http\Requests\StaffProfileRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class StaffController extends Controller
{
    // 🔐 Only admin can access
   private function checkAdmin()
{
    if (!auth()->check() || auth()->user()->role !== 'admin') {
        abort(403);
    }
}

    // 📋 Show all staff
    public function index()
{
    $this->checkAdmin();

    $staffs = User::where('role', 'staff')->get();

    return view('staff.index', compact('staffs'));
}

    public function archived()
    {
        $this->checkAdmin();

        $staffs = User::onlyTrashed()
            ->where('role', 'staff')
            ->orderByDesc('deleted_at')
            ->get();

        return view('staff.archived', compact('staffs'));
    }

    // ➕ Show create form
    public function create()
{
    $this->checkAdmin();

    return view('staff.create');
}

    // 💾 Store new staff
    public function store(StaffProfileRequest $request)
{
    $this->checkAdmin();

    $profile = $request->safe()->except('password');

    $plainTextPassword = $request->input('password');

    $staff = User::create(array_merge($profile, [
    'password' => Hash::make($plainTextPassword),
    'role' => 'staff',
]));

// ✅ AUDIT LOG
$this->logAction(
    'CREATE',
    'STAFF',
    'Created staff: ' . $staff->name
);

try {
    Mail::to($staff->email)
        ->send(new StaffCredentialMail($staff, $plainTextPassword));

    Log::info('STAFF CREDENTIAL EMAIL SENT', [
        'staff_id' => $staff->id,
        'staff_email' => $staff->email,
    ]);
} catch (\Exception $exception) {
    Log::error('STAFF CREDENTIAL EMAIL FAILED', [
        'staff_id' => $staff->id,
        'staff_email' => $staff->email,
        'exception_class' => $exception::class,
    ]);
}




    return redirect()->route('staff.index')->with('success', 'Staff created successfully.');
}


// ✏️ Edit form
public function edit(User $staff)
{
    $this->checkAdmin();
    abort_unless($staff->role === 'staff', 403);

    return view('staff.edit', compact('staff'));
}

// 💾 Update staff
public function update(StaffProfileRequest $request, User $staff)
{
    $this->checkAdmin();
    abort_unless($staff->role === 'staff', 403);

    $staff->update($request->validated());

    // ✅ AUDIT LOG
    $this->logAction(
        'UPDATE',
        'STAFF',
        'Updated staff: ' . $staff->name
    );

    return redirect()->route('staff.index')->with('success', 'Staff updated.');
}

public function destroy(User $staff)
{
    $this->checkAdmin();
    abort_unless($staff->role === 'staff', 404);

    $name = $staff->name;
    $staff->delete();

    $this->logAction(
        'ARCHIVE',
        'STAFF',
        'Staff account deactivated: ' . $name
    );

    return redirect()->route('staff.index')->with('success', 'Staff account deactivated successfully.');
}

public function resetAccount(User $staff)
{
    $this->checkAdmin();
    abort_unless($staff->role === 'staff', 404);

    $status = Password::sendResetLink([
        'email' => $staff->email,
    ]);

    if ($status === Password::RESET_LINK_SENT) {
        $this->logAction(
            'PASSWORD_RESET_REQUESTED',
            'STAFF',
            'Admin requested password reset for staff: '.$staff->name.' (ID: '.$staff->id.')'
        );

        return redirect()->route('staff.index')
            ->with('success', 'Password reset link sent to the staff member\'s registered email.');
    }

    if ($status === Password::RESET_THROTTLED) {
        return redirect()->route('staff.index')
            ->with('error', 'A password reset link was requested recently. Please wait before trying again.');
    }

    return redirect()->route('staff.index')
        ->with('error', 'Unable to send a password reset link. Please try again later.');
}

public function restore($id)
{
    $this->checkAdmin();

    $staff = User::onlyTrashed()
        ->where('role', 'staff')
        ->findOrFail($id);

    $name = $staff->name;
    $staff->restore();

    $this->logAction(
        'RESTORE',
        'STAFF',
        'Staff account reactivated: ' . $name
    );

    return redirect()->route('staff.index')->with('success', 'Staff account reactivated successfully.');
}
}
