<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminClinicAvailabilityAffectedRequest;
use App\Http\Requests\AdminClinicAvailabilityDestroyRequest;
use App\Http\Requests\AdminClinicAvailabilityStoreRequest;
use App\Http\Requests\AdminClinicAvailabilityUpdateRequest;
use App\Models\ClinicUnavailability;
use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * DEPLA Phase 3 - Admin Clinic Availability management.
 *
 * Single write surface for clinic closures (full-day, date range, or
 * selected time slots). All validation, overlap math, affected-appointment
 * computation, audit logging and transaction ownership stay in
 * ClinicAvailabilityService - this controller only: normalizes the three
 * form modes into canonical closure row(s) (contiguous slot selections
 * become ONE window each), threads the explicit confirm-affected flag, and
 * renders the Admin UI.
 *
 * AUTHORIZATION (three independent layers, same pattern as Patient
 * Account Management): the 'auth' + 'clinic' route group, AdminMiddleware
 * on the route group, and assertAdmin() below + each FormRequest
 * authorize(). The client never supplies created_by (the service stamps
 * the acting admin) and never supplies affected counts (the backend
 * re-runs affectedAppointments() inside the write transaction).
 *
 * CONCURRENCY (Part H): createClosure/updateClosure run
 * assertAffectedConfirmedAccepted with FOR UPDATE row locks on the
 * affected appointments inside their transaction, so an admin closure
 * and a staff confirmation are serialized; nothing is ever auto-cancelled.
 */
class ClinicAvailabilityController extends Controller
{
    public function __construct(
        private readonly ClinicAvailabilityService $availability,
    ) {
    }

    /**
     * Admin-only defense-in-depth check (on top of AdminMiddleware and
     * the per-action FormRequest authorize()).
     */
    private function assertAdmin(?object $actor): void
    {
        abort_unless(
            $actor instanceof User
                && ! $actor->trashed()
                && $actor->role === 'admin',
            403
        );
    }

    /**
     * Closure listing (newest first) + the 18-slot catalog the create
     * form renders. Reason is admin-internal - never serialized to any
     * patient-facing endpoint.
     */
    public function index(Request $request): View
    {
        $this->assertAdmin($request->user());

        $closures = ClinicUnavailability::with('creator')
            ->orderByDesc('start_date')
            ->orderByDesc('start_time')
            ->paginate(10)
            ->withQueryString();

        $slotStarts = $this->availability->slotStarts();
        $slotLabels = [];
        $slotEnds = [];

        foreach ($slotStarts as $start) {
            $slotLabels[$start] = $this->availability->slotLabel($start);
            $slotEnds[$start] = $this->availability->slotEnd($start);
        }

        return view('admin.clinic-unavailability.index', [
            'closures' => $closures,
            'slotStarts' => $slotStarts,
            'slotLabels' => $slotLabels,
            'slotEnds' => $slotEnds,
        ]);
    }

    /**
     * Create one or more closure rows from the three form modes inside a
     * single wrapping transaction (nested per-row transactions become
     * savepoints, so an invalid window rolls back the WHOLE batch - a
     * multi-slot submission is all-or-nothing).
     *
     * The confirm-affected flag is read from the form (set by the Admin
     * after the preview modal); the service independently re-computes the
     * affected appointments inside its transaction, so a stale or forged
     * client flag can never bypass the gate.
     */
    public function store(AdminClinicAvailabilityStoreRequest $request): RedirectResponse
    {
        $this->assertAdmin($request->user());

        $validated = $request->validated();
        $confirmAffected = filter_var($validated['confirm_affected'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reason = $validated['reason'] ?? null;

        try {
            $windows = $this->windowsFromRequest($validated);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['slot_starts' => $exception->getMessage()]);
        }

        if ($windows === []) {
            return back()->withInput()->withErrors(['slot_starts' => 'Select at least one time slot to close.']);
        }

        try {
            $created = DB::transaction(function () use ($windows, $request, $confirmAffected) {
                $closures = [];

                foreach ($windows as $window) {
                    $closures[] = $this->availability->createClosure($window, $request->user(), $confirmAffected);
                }

                return $closures;
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        } catch (\DomainException $exception) {
            // confirm-affected gate / scheduling-schema guard.
            return back()->withInput()->withErrors(['confirm_affected' => $exception->getMessage()]);
        }

        $windowCount = count($created);

        return redirect()
            ->route('admin.clinic-unavailability.index')
            ->with('success', sprintf(
                '%d clinic closure window%s saved.',
                $windowCount,
                $windowCount === 1 ? '' : 's'
            ));
    }

    /**
     * Edit one existing row. created_by is preserved by the service; the
     * affected-appointment gate re-runs on the merged result.
     */
    public function update(AdminClinicAvailabilityUpdateRequest $request, ClinicUnavailability $closure): RedirectResponse
    {
        $this->assertAdmin($request->user());

        $validated = $request->validated();
        $confirmAffected = filter_var($validated['confirm_affected'] ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $this->availability->updateClosure($closure, [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'all_day' => filter_var($validated['all_day'], FILTER_VALIDATE_BOOLEAN),
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ], $request->user(), $confirmAffected);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['confirm_affected' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clinic-unavailability.index')
            ->with('success', 'Clinic closure updated.');
    }

    /**
     * Remove (reopen) one closure row. The UI explains that overlapping
     * closure rows may still cover the same slot - removal only deletes
     * THIS row.
     */
    public function destroy(AdminClinicAvailabilityDestroyRequest $request, ClinicUnavailability $closure): RedirectResponse
    {
        $this->assertAdmin($request->user());

        $this->availability->removeClosure($closure, $request->user());

        return redirect()
            ->route('admin.clinic-unavailability.index')
            ->with('success', 'Clinic closure removed. Any overlapping closures still apply.');
    }

    /**
     * Read-only preview (JSON) for the confirm modal. Returns the
     * affected appointment split plus the overlap-warning count. The
     * backend re-computes everything at save time - these numbers are
     * informational only.
     */
    public function affected(AdminClinicAvailabilityAffectedRequest $request): JsonResponse
    {
        $this->assertAdmin($request->user());

        $validated = $request->validated();

        $input = [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'all_day' => filter_var($validated['all_day'], FILTER_VALIDATE_BOOLEAN),
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
        ];

        try {
            $affected = $this->availability->affectedAppointments($input);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Invalid closure window.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $ignoreId = $validated['ignore'] ?? null;
        $overlapping = $this->availability->overlappingClosures(
            $validated['start_date'],
            $validated['end_date'],
            $ignoreId !== null ? (int) $ignoreId : null
        );

        $occupying = (int) $affected['confirmed_count'];
        $completed = (int) $affected['completed_count'];

        return response()->json([
            'confirmed_count' => $occupying - $completed,
            'completed_count' => $completed,
            'occupying_count' => $occupying,
            'pending_count' => (int) $affected['pending_count'],
            'overlapping_count' => $overlapping->count(),
        ]);
    }

    /**
     * Normalize the three create modes into canonical closure row(s).
     * single_day / date_range => one full-day row; slots => one row per
     * contiguous run of selected slot starts (the service owns grouping).
     *
     * @return array<int, array{start_date: string, end_date: string, all_day: bool, start_time?: string, end_time?: string, reason: ?string}>
     *
     * @throws \DomainException when a selected start is off-grid
     */
    private function windowsFromRequest(array $validated): array
    {
        $reason = $validated['reason'] ?? null;
        $mode = (string) $validated['mode'];

        if ($mode === 'single_day') {
            $date = (string) $validated['date'];

            return [[
                'start_date' => $date,
                'end_date' => $date,
                'all_day' => true,
                'reason' => $reason,
            ]];
        }

        if ($mode === 'date_range') {
            return [[
                'start_date' => (string) $validated['start_date'],
                'end_date' => (string) $validated['end_date'],
                'all_day' => true,
                'reason' => $reason,
            ]];
        }

        // slots mode
        $date = (string) $validated['date'];
        $rows = [];

        foreach ($this->availability->windowsFromSlotStarts($validated['slot_starts'] ?? []) as $window) {
            $rows[] = [
                'start_date' => $date,
                'end_date' => $date,
                'all_day' => false,
                'start_time' => $window['start'],
                'end_time' => $window['end'],
                'reason' => $reason,
            ];
        }

        return $rows;
    }
}
