<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\PendingRepeatBloodPressureNotification;
use App\Notifications\ReferralClosedNotification;
use App\Notifications\ReferralCreatedNotification;
use App\Notifications\UrgentBloodPressureNotification;
use Illuminate\Support\Facades\Notification;

function makeNotificationPatient(?int $staffId = null): Patient
{
    return Patient::create([
        'first_name' => 'Cora',
        'last_name' => 'Test',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'assigned_staff_id' => $staffId,
    ]);
}

function makeNotificationVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
        'risk_level' => 'LOW',
        'urgency' => null,
        'bp_verification_status' => null,
    ], $overrides));
}

function notificationVisitPayload(int $patientId, array $overrides = []): array
{
    return array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
    ], $overrides);
}

////////////////////////
// Display / scoping
////////////////////////

test('authenticated user sees their own unread notification count', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = makeNotificationPatient();

    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);
    $admin->notify(new UrgentBloodPressureNotification($visit));
    $admin->notify(new UrgentBloodPressureNotification($visit));
    $staff->notify(new UrgentBloodPressureNotification($visit));

    // Read one of the admin's notifications to isolate the unread count.
    $admin->notifications()->first()->markAsRead();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('class="badge">1</span>', false);
});

test('user does not see another user notifications', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = makeNotificationPatient();

    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);
    $admin->notify(new UrgentBloodPressureNotification($visit));
    $staff->notify(new UrgentBloodPressureNotification($visit));

    // Each user's tray renders exactly their OWN notification row only.
    $adminPage = $this->actingAs($admin)->get(route('dashboard'));
    $adminPage->assertOk();
    expect(substr_count($adminPage->getContent(), 'class="notif-item'))->toBe(1);

    $staffPage = $this->actingAs($staff)->get(route('dashboard'));
    $staffPage->assertOk();
    expect(substr_count($staffPage->getContent(), 'class="notif-item'))->toBe(1);
});

test('marking one notification as read decreases the unread count', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeNotificationPatient();
    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);

    $user->notify(new UrgentBloodPressureNotification($visit));
    $user->notify(new UrgentBloodPressureNotification($visit));

    expect($user->unreadNotifications()->count())->toBe(2);

    $target = $user->notifications()->first();

    $response = $this->actingAs($user)->post(route('notifications.read', $target->id));

    $response->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(1);
    expect($target->refresh()->read_at)->not->toBeNull();

    // Remaining unread shows on the badge.
    $page = $this->actingAs($user)->get(route('dashboard'));
    $page->assertSee('class="badge">1</span>', false);
});

test('marking all as read affects only the authenticated user', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $other = User::factory()->create(['role' => 'staff']);
    $patient = makeNotificationPatient();
    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);

    foreach ([$user, $other] as $u) {
        $u->notify(new UrgentBloodPressureNotification($visit));
        $u->notify(new UrgentBloodPressureNotification($visit));
    }

    $response = $this->actingAs($user)->post(route('notifications.markAllRead'));

    $response->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
    expect($other->unreadNotifications()->count())->toBe(2);
});

test('notification action links resolve to authorized application routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = makeNotificationPatient($staff->id);

    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);
    $admin->notify(new UrgentBloodPressureNotification($visit));

    $notification = $admin->notifications()->first();
    $destination = $notification->data['destination'];

    expect($destination['route'])->toBe('patients.show');

    $url = route($destination['route'], $destination['parameters']);
    $this->actingAs($admin)->get($url)->assertOk();
    $this->actingAs($staff)->get($url)->assertOk();

    // The tray renders the resolved link.
    $page = $this->actingAs($admin)->get(route('dashboard'));
    $page->assertSee($url, false);
});

test('guest cannot access notification actions', function () {
    $patient = makeNotificationPatient();
    $visit = makeNotificationVisit($patient, ['urgency' => 'URGENT_CLINICAL_REVIEW']);

    $user = User::factory()->create(['role' => 'staff']);
    $user->notify(new UrgentBloodPressureNotification($visit));
    $notification = $user->notifications()->first();

    $this->post(route('notifications.read', $notification->id))->assertRedirect(route('login'));
    $this->post(route('notifications.markAllRead'))->assertRedirect(route('login'));
});

test('notification UI handles zero notifications safely', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('class="badge"', false);
    $response->assertSee('No notifications', false);
    $response->assertSee('You\'re all caught up.', false);
});

test('rendering the dashboard does not create duplicate notifications', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $this->actingAs($staff)->post(route('prenatal-visits.store'), notificationVisitPayload($patient->id, [
        'bp_sys' => 165,
        'bp_dia' => 110,
    ]));

    $before = $admin->notifications()->count() + $staff->notifications()->count();

    // Loading the dashboard twice must not create any new notifications.
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($staff)->get(route('dashboard'))->assertOk();

    expect($admin->notifications()->count() + $staff->notifications()->count())->toBe($before);
});

////////////////////////
// Event triggers
////////////////////////

test('storing an urgent bp visit notifies the assigned staff and admins once', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $this->actingAs($staff)->post(route('prenatal-visits.store'), notificationVisitPayload($patient->id, [
        'bp_sys' => 165,
        'bp_dia' => 110,
    ]));

    Notification::assertSentTo($staff, UrgentBloodPressureNotification::class);
    Notification::assertSentTo($admin, UrgentBloodPressureNotification::class);
    Notification::assertSentTo($staff, PendingRepeatBloodPressureNotification::class);
});

test('a non-urgent visit does not create urgent notifications', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $this->actingAs($staff)->post(route('prenatal-visits.store'), notificationVisitPayload($patient->id));

    Notification::assertNotSentTo($staff, UrgentBloodPressureNotification::class);
    Notification::assertNotSentTo($admin, UrgentBloodPressureNotification::class);
    Notification::assertNotSentTo($staff, PendingRepeatBloodPressureNotification::class);
});

test('updating a visit that is already urgent does not create a duplicate notification', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $visit = makeNotificationVisit($patient, [
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_verification_status' => \App\Services\BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT,
    ]);

    $this->actingAs($staff)->put(route('prenatal-visits.update', $visit->id), notificationVisitPayload($patient->id, [
        'bp_sys' => 165,
        'bp_dia' => 110,
    ]));

    Notification::assertNotSentTo($staff, UrgentBloodPressureNotification::class);
    Notification::assertNotSentTo($admin, UrgentBloodPressureNotification::class);
    Notification::assertNotSentTo($staff, PendingRepeatBloodPressureNotification::class);
});

test('referral creation notifies admins but not the acting staff', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $this->actingAs($staff)->post(route('referrals.store'), [
        'patient_id' => $patient->id,
        'referred_to' => 'City General Hospital',
        'reason' => 'Ongoing monitoring required',
        'date_referred' => now()->toDateString(),
    ]);

    Notification::assertSentTo($admin, ReferralCreatedNotification::class);
    Notification::assertNotSentTo($staff, ReferralCreatedNotification::class);
});

test('referral closure notifies admins', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = makeNotificationPatient($staff->id);

    $referral = Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $staff->id,
        'referred_to' => 'City General Hospital',
        'reason' => 'Ongoing monitoring required',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $this->actingAs($staff)->post(route('referrals.complete', $referral->id));

    Notification::assertSentTo($admin, ReferralClosedNotification::class);
});