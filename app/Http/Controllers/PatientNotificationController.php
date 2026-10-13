<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DEPLA Phase 3B.4 - Patient Portal Notifications.
 *
 * A read + mark-as-read surface for the authenticated patient's OWN
 * database notifications. Identity is resolved ONLY from the authenticated
 * user (never from a URL/query parameter), and every query is scoped
 * through the User's own notifications() morphMany relation, so a
 * notification id belonging to another patient resolves to a 404 — no
 * cross-patient access is possible through URL manipulation.
 *
 * Eligibility is enforced by the route middleware chain ['auth',
 * 'patient-portal-account', 'must-change-password', 'no-store'] (identical
 * to the Appointments / Profile portal routes); the explicit role check
 * below keeps the in-controller 403 behaviour and adds defence in depth,
 * matching PatientAppointmentController's portalUserOrAbort().
 *
 * Clinic-role users keep the existing clinic notification tray (same
 * notifications table, scoped to their own rows) — this controller never
 * serves them.
 */
class PatientNotificationController extends Controller
{
    /**
     * GET /patient/notifications — the authenticated patient's own
     * notifications, newest first, paginated. Unread items are visually
     * distinguished in the view; a Mark-all-as-read action is offered
     * whenever at least one unread row exists.
     */
    public function index(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);

        $notifications = $user->notifications()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('patient.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /patient/notifications/{id}/read — mark ONE notification as
     * read. Scoped to the authenticated user's own notifications via the
     * relationship, so another user's notification id can never be
     * modified (it resolves to a 404). Mirrors the clinic-side
     * NotificationController::markAsRead() authorization model.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $this->portalUserOrAbort($request);

        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return redirect()->back();
    }

    /**
     * POST /patient/notifications/mark-all-read — mark every unread
     * notification of the CURRENT user as read. Only ever affects the
     * authenticated user's own rows (inherently self-scoped through the
     * unreadNotifications() relation). Mirrors the clinic-side
     * NotificationController::markAllAsRead().
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->portalUserOrAbort($request);

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }

    /**
     * Non-patient roles are denied here because this surface exposes
     * patient-owned notification data. The portal middleware deliberately
     * passes clinic roles through (so other portal pages can redirect
     * them), so the explicit check stays — same pattern as
     * PatientAppointmentController::portalUserOrAbort().
     */
    private function portalUserOrAbort(Request $request): User
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            abort(403);
        }

        return $user;
    }
}
