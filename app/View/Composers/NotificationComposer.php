<?php

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * Provides the topbar notification tray data for the shared authenticated
 * layout. Loads only the latest 6 notifications and the unread count for the
 * currently authenticated user; guests get an empty state.
 */
class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            $view->with('notifications', collect());
            $view->with('unreadNotificationCount', 0);

            return;
        }

        $view->with('notifications', $user->notifications()->latest()->limit(6)->get());
        $view->with('unreadNotificationCount', $user->unreadNotifications()->count());
    }
}