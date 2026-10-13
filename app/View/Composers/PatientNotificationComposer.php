<?php

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * DEPLA Phase 3B.4 — provides the Patient Portal notification-bell data
 * for the shared portal layout: the five most recent notifications and the
 * unread count for the authenticated PATIENT account. Clinic-role users
 * (who the portal middleware passes through) and guests get an empty
 * state, so the bell never leaks clinic-tray data into the portal chrome.
 *
 * Mirrors the clinic-side NotificationComposer, which is bound only to
 * layouts.app (AppServiceProvider). All queries are scoped through the
 * authenticated user's own notifications() relation — no user_id is ever
 * read from the request.
 */
class PatientNotificationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user || $user->role !== 'patient') {
            $view->with('patientNotifications', collect());
            $view->with('patientUnreadNotificationCount', 0);

            return;
        }

        $view->with('patientNotifications', $user->notifications()->latest()->limit(5)->get());
        $view->with('patientUnreadNotificationCount', $user->unreadNotifications()->count());
    }
}
