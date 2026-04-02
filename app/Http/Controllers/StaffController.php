<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    // ➕ Show create form
    public function create()
{
    $this->checkAdmin();

    return view('staff.create');
}

    // 💾 Store new staff
    public function store(Request $request)
{
    $this->checkAdmin();

    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6',
    ]);

    $staff = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'role' => 'staff',
]);

// ✅ AUDIT LOG
$this->logAction(
    'CREATE',
    'STAFF',
    'Created staff: ' . $staff->name
);





    return redirect()->route('staff.index')->with('success', 'Staff created successfully.');
}


// ✏️ Edit form
public function edit(User $staff)
{
    $this->checkAdmin();

    return view('staff.edit', compact('staff'));
}

// 💾 Update staff
public function update(Request $request, User $staff)
{
    $this->checkAdmin();

    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $staff->id,
    ]);

    $staff->update([
        'name' => $request->name,
        'email' => $request->email,
    ]);

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

    // ✅ save BEFORE delete
    $name = $staff->name;

    $staff->delete();

    // ✅ AUDIT LOG
    $this->logAction(
        'DELETE',
        'STAFF',
        'Deleted staff: ' . $name
    );

    return redirect()->route('staff.index')->with('success', 'Staff deleted.');
}
}